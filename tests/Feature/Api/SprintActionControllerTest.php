<?php

use App\Enums\ActivityType;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Enums\UnfinishedTaskAction;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\SprintService;
use Laravel\Sanctum\Sanctum;

it('requires authentication for sprint actions', function () {
    $sprint = Sprint::factory()->create();

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")
        ->assertUnauthorized();
});

it('starts a planned sprint and records its start date', function () {
    $sprint = Sprint::factory()->create([
        'status' => SprintStatus::Planned,
        'start_date' => null,
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', SprintStatus::Active->value)
        ->assertJsonPath('data.start_date', today()->toDateString());

    $this->assertDatabaseHas('activities', [
        'subject_id' => $sprint->id,
        'type' => ActivityType::SprintStarted->value,
    ]);
});

it('preserves an existing start date when starting a sprint', function () {
    $sprint = Sprint::factory()->create([
        'status' => SprintStatus::Planned,
        'start_date' => '2026-10-01',
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")
        ->assertOk()
        ->assertJsonPath('data.start_date', '2026-10-01');
});

it('rejects starting a sprint that is not planned', function () {
    $sprint = Sprint::factory()->create(['status' => SprintStatus::Completed]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")
        ->assertUnprocessable()
        ->assertInvalid(['status']);
});

it('rejects starting a second active sprint in the project', function () {
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $plannedSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/sprints/{$plannedSprint->id}/start")
        ->assertUnprocessable()
        ->assertInvalid(['status']);

    expect($plannedSprint->refresh()->status)->toBe(SprintStatus::Planned);
});

it('completes a sprint and records its completion timestamp', function () {
    $sprint = Sprint::factory()->create([
        'status' => SprintStatus::Active,
        'completed_at' => null,
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/complete")
        ->assertUnprocessable()
        ->assertInvalid(['unfinished_action']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/complete", [
        'unfinished_action' => UnfinishedTaskAction::Backlog->value,
    ])
        ->assertOk()
        ->assertJsonPath('sprint.status', SprintStatus::Completed->value)
        ->assertJsonPath('sprint.completed_at', fn (mixed $value): bool => is_string($value))
        ->assertJsonPath('summary.planned_points', 0)
        ->assertJsonPath('summary.completed_points', 0)
        ->assertJsonPath('summary.completed_tasks', 0)
        ->assertJsonPath('summary.unfinished_tasks', 0)
        ->assertJsonCount(0, 'moved_tasks')
        ->assertJsonMissingPath('completed_tasks');

    expect($sprint->refresh()->completed_at)->not->toBeNull();
    $this->assertDatabaseHas('activities', [
        'subject_id' => $sprint->id,
        'type' => ActivityType::SprintCompleted->value,
    ]);
});

it('moves unfinished tasks to backlog and records the completion totals', function () {
    $sprint = Sprint::factory()->create(['status' => SprintStatus::Active]);
    $completedTask = Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Done,
        'story_points' => 8,
    ]);
    $todoTask = Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Todo,
        'story_points' => 3,
    ]);
    $blockedTask = Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Blocked,
        'story_points' => 2,
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/complete", [
        'unfinished_action' => UnfinishedTaskAction::Backlog->value,
        'include_completed_tasks' => true,
    ])->assertOk()
        ->assertJsonPath('sprint.status', SprintStatus::Completed->value)
        ->assertJsonPath('summary.planned_points', 13)
        ->assertJsonPath('summary.completed_points', 8)
        ->assertJsonPath('summary.completed_tasks', 1)
        ->assertJsonPath('summary.unfinished_tasks', 2)
        ->assertJsonCount(2, 'moved_tasks')
        ->assertJsonCount(1, 'completed_tasks')
        ->assertJsonPath('completed_tasks.0.id', $completedTask->id)
        ->assertJsonMissingPath('moved_tasks.0.description')
        ->assertJsonMissingPath('completed_tasks.0.description');

    expect($completedTask->refresh()->sprint_id)->toBe($sprint->id)
        ->and($completedTask->status)->toBe(TaskStatus::Done)
        ->and($todoTask->refresh()->sprint_id)->toBeNull()
        ->and($todoTask->status)->toBe(TaskStatus::Backlog)
        ->and($blockedTask->refresh()->sprint_id)->toBeNull()
        ->and($blockedTask->status)->toBe(TaskStatus::Backlog);

    $activity = Activity::query()->where('type', ActivityType::SprintCompleted)->firstOrFail();

    expect($activity->metadata)->toMatchArray([
        'planned_points' => 13,
        'completed_points' => 8,
        'completed_tasks' => 1,
        'unfinished_tasks' => 2,
        'unfinished_action' => UnfinishedTaskAction::Backlog->value,
        'next_sprint_id' => null,
    ]);
});

it('moves unfinished tasks to a valid next sprint', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $nextSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    $unfinishedTask = Task::factory()->for($project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::InProgress,
    ]);
    $completedTask = Task::factory()->for($project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Done,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/sprints/{$sprint->id}/complete", [
        'unfinished_action' => UnfinishedTaskAction::NextSprint->value,
        'next_sprint_id' => $nextSprint->id,
    ])->assertOk()
        ->assertJsonCount(1, 'moved_tasks')
        ->assertJsonPath('moved_tasks.0.id', $unfinishedTask->id)
        ->assertJsonPath('moved_tasks.0.sprint_id', $nextSprint->id)
        ->assertJsonMissingPath('completed_tasks');

    expect($unfinishedTask->refresh()->sprint_id)->toBe($nextSprint->id)
        ->and($unfinishedTask->status)->toBe(TaskStatus::InProgress)
        ->and($completedTask->refresh()->sprint_id)->toBe($sprint->id);
});

it('rejects completion unless the sprint is active', function () {
    $sprint = Sprint::factory()->create(['status' => SprintStatus::Planned]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/complete", [
        'unfinished_action' => UnfinishedTaskAction::Backlog->value,
    ])->assertUnprocessable()
        ->assertInvalid(['status']);

    expect($sprint->refresh()->status)->toBe(SprintStatus::Planned)
        ->and($sprint->completed_at)->toBeNull();
});

it('rejects an invalid completed or cross project next sprint', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $completedSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Completed]);
    $otherSprint = Sprint::factory()->create(['status' => SprintStatus::Planned]);
    Sanctum::actingAs($project->user, ['*']);
    $url = "/api/projects/{$project->id}/sprints/{$sprint->id}/complete";

    foreach ([$completedSprint->id, $otherSprint->id, $sprint->id] as $nextSprintId) {
        $this->postJson($url, [
            'unfinished_action' => UnfinishedTaskAction::NextSprint->value,
            'next_sprint_id' => $nextSprintId,
        ])->assertUnprocessable()->assertInvalid(['next_sprint_id']);
    }

    expect($sprint->refresh()->status)->toBe(SprintStatus::Active);
});

it('rolls back task moves and sprint completion when activity logging fails', function () {
    $sprint = Sprint::factory()->create(['status' => SprintStatus::Active]);
    $task = Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Todo,
    ]);
    $activityService = Mockery::mock(ActivityService::class);
    $activityService->shouldReceive('sprintCompleted')->once()->andThrow(new RuntimeException('Logging failed'));
    $service = new SprintService($activityService);

    expect(fn () => $service->complete($sprint, UnfinishedTaskAction::Backlog))
        ->toThrow(RuntimeException::class, 'Logging failed');

    expect($sprint->refresh()->status)->toBe(SprintStatus::Active)
        ->and($sprint->completed_at)->toBeNull()
        ->and($task->refresh()->sprint_id)->toBe($sprint->id)
        ->and($task->status)->toBe(TaskStatus::Todo);
});

it('returns 404 for sprint actions through a different project', function () {
    $sprint = Sprint::factory()->create();
    $otherProject = Project::factory()->for($sprint->project->user)->create();
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$otherProject->id}/sprints/{$sprint->id}/start")->assertNotFound();
    $this->postJson("/api/projects/{$otherProject->id}/sprints/{$sprint->id}/complete")->assertNotFound();
});

it('hides sprint actions from another user', function () {
    $sprint = Sprint::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")->assertNotFound();
    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/complete")->assertNotFound();
});
