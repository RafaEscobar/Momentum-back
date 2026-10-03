<?php

use App\Http\Resources\BoardTaskResource;
use App\Http\Resources\ProjectNoteSummaryResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Http\Resources\SprintSummaryResource;
use App\Http\Resources\TaskSummaryResource;
use App\Models\Activity;
use App\Models\ChecklistItem;
use App\Models\GeneralNote;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Schedule;
use Laravel\Sanctum\Sanctum;

it('omits long content from every summary resource', function () {
    $project = Project::factory()->create(['description' => 'private project description']);
    $sprint = Sprint::factory()->for($project)->create(['goal' => 'private sprint goal']);
    $task = Task::factory()->for($project)->for($sprint)->create(['description' => 'private task description']);
    $note = ProjectNote::factory()->for($project)->create(['content' => 'private note content']);

    $project->loadCount('tasks');
    $task->setAttribute('checklist_items_count', 0);
    $task->setAttribute('completed_checklist_items_count', 0);
    $task->setRelation('tags', collect());

    expect((new ProjectSummaryResource($project))->resolve())->not->toHaveKey('description')
        ->and((new TaskSummaryResource($task))->resolve())->not->toHaveKey('description')
        ->and((new BoardTaskResource($task))->resolve())->not->toHaveKey('description')
        ->and((new SprintSummaryResource($sprint))->resolve())->not->toHaveKey('goal')
        ->and((new ProjectNoteSummaryResource($note))->resolve())->not->toHaveKey('content');
});

it('never serializes credentials or personal access token relations from a user', function () {
    $user = User::factory()->create(['remember_token' => 'remember-secret']);
    $plainTextToken = $user->createToken('privacy-test')->plainTextToken;
    $serialized = $user->load('tokens')->toArray();

    expect($serialized)
        ->not->toHaveKeys(['password', 'remember_token', 'tokens'])
        ->and(json_encode($serialized))->not->toContain($plainTextToken);

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonMissingPath('user.password')
        ->assertJsonMissingPath('user.remember_token')
        ->assertJsonMissingPath('user.tokens');
});

it('selects only expired activities for retention pruning', function () {
    config()->set('security.activity_retention_days', 30);
    $expired = Activity::factory()->create(['created_at' => now()->subDays(31)]);
    $current = Activity::factory()->create(['created_at' => now()->subDays(29)]);

    expect((new Activity)->prunable()->pluck('id')->all())
        ->toContain($expired->id)
        ->not->toContain($current->id);

    $scheduledCommands = collect(Schedule::events())->pluck('command')->implode("\n");

    expect($scheduledCommands)->toContain('model:prune')->toContain(Activity::class);
});

it('cascades all owned data when a user is deleted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $sprint = Sprint::factory()->for($project)->create();
    $task = Task::factory()->for($project)->for($sprint)->create();
    $checklistItem = ChecklistItem::factory()->for($task)->create();
    $note = ProjectNote::factory()->for($project)->create();
    $generalNote = GeneralNote::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create();
    $task->tags()->attach($tag);
    $activity = Activity::factory()->for($user)->for($project)->create();
    $tokenId = $user->createToken('cascade-test')->accessToken->id;

    $user->delete();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    $this->assertDatabaseMissing('checklist_items', ['id' => $checklistItem->id]);
    $this->assertDatabaseMissing('project_notes', ['id' => $note->id]);
    $this->assertDatabaseMissing('general_notes', ['id' => $generalNote->id]);
    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id, 'tag_id' => $tag->id]);
});

it('cascades project and task children without deleting user tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->create();
    $item = ChecklistItem::factory()->for($task)->create();
    $task->tags()->attach($tag);

    $task->delete();

    $this->assertDatabaseMissing('checklist_items', ['id' => $item->id]);
    $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id]);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);

    $sprint = Sprint::factory()->for($project)->create();
    $note = ProjectNote::factory()->for($project)->create();
    $activity = Activity::factory()->for($user)->for($project)->create();
    $project->delete();

    $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
    $this->assertDatabaseMissing('project_notes', ['id' => $note->id]);
    $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});

it('documents summary collections without real credentials or personal data', function () {
    $specification = file_get_contents(base_path('docs/openapi.yaml'));

    expect($specification)
        ->toContain('#/components/schemas/ProjectSummary')
        ->toContain('#/components/schemas/TaskSummary')
        ->toContain('#/components/schemas/SprintSummary')
        ->toContain('#/components/schemas/NoteSummary')
        ->not->toMatch('/example:\s*[^\n]*@/i')
        ->not->toMatch('/example:\s*[^\n]*(password|secret|bearer|token)/i');
});
