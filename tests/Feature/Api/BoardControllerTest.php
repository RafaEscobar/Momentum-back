<?php

use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('uses the active sprint by default and groups tasks by status', function () {
    $project = Project::factory()->create();
    $activeSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $otherSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    $backlogTask = Task::factory()->for($project)->create([
        'sprint_id' => null,
        'status' => TaskStatus::Backlog,
        'position' => 2,
    ]);
    $sprintTask = Task::factory()->for($project)->create([
        'sprint_id' => $activeSprint->id,
        'status' => TaskStatus::Todo,
        'position' => 1,
    ]);
    Task::factory()->for($project)->create([
        'sprint_id' => $otherSprint->id,
        'status' => TaskStatus::Todo,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/board")
        ->assertOk()
        ->assertJsonPath('sprint.id', $activeSprint->id)
        ->assertJsonCount(1, 'backlog')
        ->assertJsonPath('backlog.0.id', $backlogTask->id)
        ->assertJsonCount(1, 'todo')
        ->assertJsonPath('todo.0.id', $sprintTask->id)
        ->assertJsonCount(0, 'in_progress')
        ->assertJsonCount(0, 'blocked')
        ->assertJsonCount(0, 'done');
});

it('allows selecting a sprint from the same project', function () {
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $selectedSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    $task = Task::factory()->for($project)->create([
        'sprint_id' => $selectedSprint->id,
        'status' => TaskStatus::InProgress,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/board?sprint_id={$selectedSprint->id}")
        ->assertOk()
        ->assertJsonPath('sprint.id', $selectedSprint->id)
        ->assertJsonPath('in_progress.0.id', $task->id);
});

it('orders board cards and includes checklist summary story points and tags', function () {
    $project = Project::factory()->create();
    $laterTask = Task::factory()->for($project)->create([
        'status' => TaskStatus::Backlog,
        'position' => 2,
        'story_points' => 8,
    ]);
    $firstTask = Task::factory()->for($project)->create([
        'status' => TaskStatus::Backlog,
        'position' => 1,
        'story_points' => 3,
    ]);
    ChecklistItem::factory()->for($firstTask)->create(['is_completed' => true]);
    ChecklistItem::factory()->for($firstTask)->create(['is_completed' => false]);
    $tag = Tag::factory()->for($project->user)->create(['name' => 'API', 'color' => '#2563EB']);
    $firstTask->tags()->attach($tag);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/board")
        ->assertOk()
        ->assertJsonPath('backlog.0.id', $firstTask->id)
        ->assertJsonPath('backlog.0.story_points', 3)
        ->assertJsonPath('backlog.0.checklist.total', 2)
        ->assertJsonPath('backlog.0.checklist.completed', 1)
        ->assertJsonPath('backlog.0.tags.0.id', $tag->id)
        ->assertJsonPath('backlog.0.tags.0.name', 'API')
        ->assertJsonPath('backlog.1.id', $laterTask->id)
        ->assertJsonMissingPath('backlog.0.description');
});

it('reflects card reordering and movement between board columns', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $task = Task::factory()->for($project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Todo,
        'position' => 3,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => [[
            'id' => $task->id,
            'status' => TaskStatus::InProgress->value,
            'position' => 1,
        ]],
    ])->assertOk();

    $this->getJson("/api/projects/{$project->id}/board")
        ->assertOk()
        ->assertJsonCount(0, 'todo')
        ->assertJsonCount(1, 'in_progress')
        ->assertJsonPath('in_progress.0.id', $task->id)
        ->assertJsonPath('in_progress.0.position', 1);
});

it('rejects a sprint from another project', function () {
    $project = Project::factory()->create();
    $otherSprint = Sprint::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/board?sprint_id={$otherSprint->id}")
        ->assertUnprocessable()
        ->assertInvalid(['sprint_id']);
});

it('hides another users board', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson("/api/projects/{$project->id}/board")->assertNotFound();
});
