<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to reorder tasks', function () {
    $project = Project::factory()->create();

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => [],
    ])->assertUnauthorized();
});

it('reorders tasks and updates their statuses atomically', function () {
    $project = Project::factory()->create();
    $firstTask = Task::factory()->for($project)->create([
        'status' => TaskStatus::Todo,
        'position' => 1,
    ]);
    $secondTask = Task::factory()->for($project)->create([
        'status' => TaskStatus::InProgress,
        'position' => 2,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => [
            ['id' => $secondTask->id, 'status' => TaskStatus::Done->value, 'position' => 1],
            ['id' => $firstTask->id, 'status' => TaskStatus::InProgress->value, 'position' => 2],
        ],
    ])->assertOk()
        ->assertJsonPath('data.0.id', $secondTask->id)
        ->assertJsonPath('data.0.status', TaskStatus::Done->value)
        ->assertJsonPath('data.0.position', 1)
        ->assertJsonPath('data.1.id', $firstTask->id)
        ->assertJsonPath('data.1.status', TaskStatus::InProgress->value)
        ->assertJsonPath('data.1.position', 2);

    expect($secondTask->refresh()->completed_at)->not->toBeNull()
        ->and($firstTask->refresh()->completed_at)->toBeNull();
});

it('validates every reorder item before writing', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create([
        'status' => TaskStatus::Todo,
        'position' => 1,
    ]);
    $foreignTask = Task::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => [
            ['id' => $task->id, 'status' => TaskStatus::Done->value, 'position' => 9],
            ['id' => $foreignTask->id, 'status' => TaskStatus::Done->value, 'position' => 10],
        ],
    ])->assertUnprocessable()
        ->assertInvalid(['tasks']);

    expect($task->refresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->position)->toBe(1)
        ->and($task->completed_at)->toBeNull();
});

it('rejects malformed or duplicate reorder items', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => [
            ['id' => $task->id, 'status' => 'review', 'position' => -1],
            ['id' => $task->id, 'status' => TaskStatus::Todo->value, 'position' => 2],
        ],
    ])->assertUnprocessable()
        ->assertInvalid(['tasks.0.status', 'tasks.0.position', 'tasks.0.id', 'tasks.1.id']);
});

it('hides task reordering for another users project', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => [['id' => 1, 'status' => TaskStatus::Todo->value, 'position' => 1]],
    ])->assertNotFound();
});
