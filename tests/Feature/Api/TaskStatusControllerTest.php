<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to change task status', function () {
    $task = Task::factory()->create();

    $this->patchJson("/api/projects/{$task->project_id}/tasks/{$task->id}/status", [
        'status' => TaskStatus::Done->value,
    ])->assertUnauthorized();
});

it('marks a task done and records completed at', function () {
    $task = Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'completed_at' => null,
    ]);
    Sanctum::actingAs($task->project->user, ['*']);

    $this->patchJson("/api/projects/{$task->project_id}/tasks/{$task->id}/status", [
        'status' => TaskStatus::Done->value,
    ])->assertOk()
        ->assertJsonPath('data.status', TaskStatus::Done->value)
        ->assertJsonPath('data.completed_at', fn (mixed $value): bool => is_string($value));

    expect($task->refresh()->completed_at)->not->toBeNull();
});

it('clears completed at when a task leaves done', function () {
    $task = Task::factory()->create([
        'status' => TaskStatus::Done,
        'completed_at' => now()->subDay(),
    ]);
    Sanctum::actingAs($task->project->user, ['*']);

    $this->patchJson("/api/projects/{$task->project_id}/tasks/{$task->id}/status", [
        'status' => TaskStatus::InProgress->value,
    ])->assertOk()
        ->assertJsonPath('data.status', TaskStatus::InProgress->value)
        ->assertJsonPath('data.completed_at', null);
});

it('validates task status', function () {
    $task = Task::factory()->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->patchJson("/api/projects/{$task->project_id}/tasks/{$task->id}/status", [
        'status' => 'review',
    ])->assertUnprocessable()
        ->assertInvalid(['status']);
});

it('returns 404 for status changes through a different project', function () {
    $task = Task::factory()->create();
    $otherProject = Project::factory()->for($task->project->user)->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->patchJson("/api/projects/{$otherProject->id}/tasks/{$task->id}/status", [
        'status' => TaskStatus::Done->value,
    ])->assertNotFound();
});

it('hides status changes from another user', function () {
    $task = Task::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson("/api/projects/{$task->project_id}/tasks/{$task->id}/status", [
        'status' => TaskStatus::Done->value,
    ])->assertNotFound();
});
