<?php

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('ignores protected and unknown properties on resource writes', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    Sanctum::actingAs($user, ['*']);

    $projectId = $this->postJson('/api/projects', [
        'name' => 'Owned project',
        'user_id' => $otherUser->id,
        'completed_at' => now()->toISOString(),
        'administrative' => true,
    ])->assertCreated()->json('data.id');
    $project = Project::findOrFail($projectId);

    $taskId = $this->postJson("/api/projects/{$project->id}/tasks", [
        'title' => 'Owned task',
        'project_id' => $otherProject->id,
        'completed_at' => now()->toISOString(),
        'user_id' => $otherUser->id,
    ])->assertCreated()->json('data.id');
    $task = Task::findOrFail($taskId);

    $sprintId = $this->postJson("/api/projects/{$project->id}/sprints", [
        'name' => 'Owned sprint',
        'project_id' => $otherProject->id,
        'completed_at' => now()->toISOString(),
    ])->assertCreated()->json('data.id');
    $noteId = $this->postJson("/api/projects/{$project->id}/notes", [
        'title' => 'Owned note',
        'content' => 'Safe content',
        'project_id' => $otherProject->id,
    ])->assertCreated()->json('data.id');
    $tagId = $this->postJson('/api/tags', [
        'name' => 'Owned tag',
        'color' => '#123456',
        'user_id' => $otherUser->id,
    ])->assertCreated()->json('data.id');
    $itemId = $this->postJson("/api/tasks/{$task->id}/checklist", [
        'title' => 'Owned item',
        'task_id' => Task::factory()->create()->id,
    ])->assertCreated()->json('data.id');

    expect($project->user_id)->toBe($user->id)
        ->and($task->project_id)->toBe($project->id)
        ->and($task->completed_at)->toBeNull()
        ->and(Sprint::findOrFail($sprintId)->project_id)->toBe($project->id)
        ->and(Sprint::findOrFail($sprintId)->completed_at)->toBeNull()
        ->and(ProjectNote::findOrFail($noteId)->project_id)->toBe($project->id)
        ->and(Tag::findOrFail($tagId)->user_id)->toBe($user->id)
        ->and(ChecklistItem::findOrFail($itemId)->task_id)->toBe($task->id);
});

it('keeps explicit fillable and hidden attributes on every model', function () {
    $expectedFillable = [
        Activity::class => ['user_id', 'type', 'description', 'metadata'],
        ChecklistItem::class => ['title', 'is_completed', 'position'],
        Project::class => ['name', 'description', 'status', 'priority', 'color', 'icon', 'start_date', 'target_date'],
        ProjectNote::class => ['title', 'content'],
        Sprint::class => ['name', 'goal', 'start_date', 'end_date', 'status', 'completed_at'],
        Tag::class => ['name', 'color'],
        Task::class => ['sprint_id', 'title', 'description', 'type', 'priority', 'status', 'story_points', 'position', 'completed_at'],
        User::class => ['name', 'email', 'password'],
    ];

    foreach ($expectedFillable as $modelClass => $fillable) {
        expect((new $modelClass)->getFillable())->toBe($fillable);
    }

    expect((new User)->getHidden())->toContain('password', 'remember_token');
});

it('rejects unknown keys inside bulk operation items before writing', function () {
    $task = Task::factory()->create(['position' => 1]);
    $item = ChecklistItem::factory()->for($task)->create(['position' => 1]);
    Sanctum::actingAs($task->project->user, ['*']);

    $this->patchJson("/api/projects/{$task->project_id}/tasks/reorder", [
        'tasks' => [[
            'id' => $task->id,
            'status' => TaskStatus::Done->value,
            'position' => 2,
            'project_id' => Project::factory()->create()->id,
        ]],
    ])->assertUnprocessable()->assertInvalid(['tasks.0']);

    $this->patchJson("/api/tasks/{$task->id}/checklist/reorder", [
        'items' => [[
            'id' => $item->id,
            'position' => 2,
            'task_id' => Task::factory()->create()->id,
        ]],
    ])->assertUnprocessable()->assertInvalid(['items.0']);

    expect($task->refresh()->position)->toBe(1)
        ->and($task->status)->not->toBe(TaskStatus::Done)
        ->and($item->refresh()->position)->toBe(1);
});

it('treats SQL syntax and LIKE wildcards as literal search input', function () {
    $project = Project::factory()->create(['name' => 'Visible project']);
    $sprint = Sprint::factory()->for($project)->create(['name' => 'Visible sprint']);
    Task::factory()->for($project)->create(['title' => 'Visible task', 'sprint_id' => null]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson('/api/projects?search=%25')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/projects/{$project->id}/sprints?search=%25")
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/projects/{$project->id}/tasks?search=%25")
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/projects/{$project->id}/backlog?search=%25")
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/search?q=%25_')->assertOk()
        ->assertJsonCount(0, 'projects')
        ->assertJsonCount(0, 'tasks')
        ->assertJsonCount(0, 'notes');
    $this->getJson('/api/projects?search='.urlencode("' OR 1=1 --"))
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/projects?status='.urlencode("active' OR 1=1 --"))
        ->assertUnprocessable()->assertInvalid(['status']);
    $this->getJson("/api/projects/{$project->id}/activities?date_from=".urlencode("2026-01-01' OR 1=1 --"))
        ->assertUnprocessable()->assertInvalid(['date_from']);
    $this->getJson('/api/projects/'.urlencode($sprint->id.' OR 1=1'))->assertNotFound();
});

it('rejects malformed JSON incorrect types and excessive nesting', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->call(
        'POST',
        '/api/projects',
        server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        content: '{"name":',
    )->assertUnprocessable();

    $this->postJson('/api/projects', [
        'name' => ['nested' => ['too' => ['deep' => true]]],
        'description' => ['not' => 'a string'],
        'icon' => ['not' => 'a string'],
    ])->assertUnprocessable()->assertInvalid(['name', 'description', 'icon']);
});

it('accepts exact text limits for every resource family', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson('/api/projects', [
        'name' => str_repeat('p', 255),
        'description' => str_repeat('d', 10000),
        'icon' => str_repeat('i', 50),
        'color' => '#ABCDEF',
    ])->assertCreated();
    $this->postJson("/api/projects/{$project->id}/tasks", [
        'title' => str_repeat('t', 255),
        'description' => str_repeat('d', 10000),
    ])->assertCreated();
    $this->postJson("/api/projects/{$project->id}/sprints", [
        'name' => str_repeat('s', 255),
        'goal' => str_repeat('g', 10000),
    ])->assertCreated();
    $this->postJson("/api/projects/{$project->id}/notes", [
        'title' => str_repeat('n', 255),
        'content' => str_repeat('c', 50000),
    ])->assertCreated();
    $this->postJson('/api/tags', [
        'name' => str_repeat('g', 50),
        'color' => '#123456',
    ])->assertCreated();
    $this->postJson('/api/tasks/'.Task::factory()->for($project)->create()->id.'/checklist', [
        'title' => str_repeat('c', 255),
    ])->assertCreated();
});

it('rejects text values one character above each maximum', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson('/api/projects', [
        'name' => str_repeat('p', 256),
        'description' => str_repeat('d', 10001),
        'icon' => str_repeat('i', 51),
        'color' => '#1234567',
    ])->assertUnprocessable()->assertInvalid(['name', 'description', 'icon', 'color']);
    $this->postJson("/api/projects/{$project->id}/tasks", [
        'title' => str_repeat('t', 256),
        'description' => str_repeat('d', 10001),
    ])->assertUnprocessable()->assertInvalid(['title', 'description']);
    $this->postJson("/api/projects/{$project->id}/sprints", [
        'name' => str_repeat('s', 256),
        'goal' => str_repeat('g', 10001),
    ])->assertUnprocessable()->assertInvalid(['name', 'goal']);
    $this->postJson("/api/projects/{$project->id}/notes", [
        'title' => str_repeat('n', 256),
        'content' => str_repeat('c', 50001),
    ])->assertUnprocessable()->assertInvalid(['title', 'content']);
    $this->postJson('/api/tags', [
        'name' => str_repeat('g', 51),
        'color' => 'red',
    ])->assertUnprocessable()->assertInvalid(['name', 'color']);
    $this->postJson("/api/tasks/{$task->id}/checklist", [
        'title' => str_repeat('c', 256),
    ])->assertUnprocessable()->assertInvalid(['title']);
});

it('rejects negative overflowing and unsupported numeric values', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    $item = ChecklistItem::factory()->for($task)->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/tasks", [
        'title' => 'Extreme task',
        'story_points' => 255,
        'position' => 4294967296,
        'sprint_id' => -1,
    ])->assertUnprocessable()->assertInvalid(['story_points', 'position', 'sprint_id']);
    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => [[
            'id' => -1,
            'status' => TaskStatus::Todo->value,
            'position' => 4294967296,
        ]],
    ])->assertUnprocessable()->assertInvalid(['tasks.0.id', 'tasks.0.position']);
    $this->patchJson("/api/tasks/{$task->id}/checklist/reorder", [
        'items' => [[
            'id' => '9223372036854775808',
            'position' => -1,
        ]],
    ])->assertUnprocessable()->assertInvalid(['items.0.id', 'items.0.position']);

    expect($task->refresh()->position)->not->toBe(4294967296)
        ->and($item->refresh()->position)->not->toBe(-1);
});
