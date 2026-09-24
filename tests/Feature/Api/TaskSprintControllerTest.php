<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication to change a tasks sprint', function () {
    $task = Task::factory()->create();

    $this->patchJson("/api/projects/{$task->project_id}/tasks/{$task->id}/sprint", ['sprint_id' => null])
        ->assertUnauthorized();
});

it('moves a backlog task into a sprint and changes its status to todo', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create();
    $task = Task::factory()->for($project)->create([
        'sprint_id' => null,
        'status' => TaskStatus::Backlog,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/{$task->id}/sprint", [
        'sprint_id' => $sprint->id,
    ])->assertOk()
        ->assertJsonPath('data.sprint_id', $sprint->id)
        ->assertJsonPath('data.status', TaskStatus::Todo->value);
});

it('preserves a non backlog status when moving between sprints', function () {
    $project = Project::factory()->create();
    $firstSprint = Sprint::factory()->for($project)->create();
    $secondSprint = Sprint::factory()->for($project)->create();
    $task = Task::factory()->for($project)->create([
        'sprint_id' => $firstSprint->id,
        'status' => TaskStatus::InProgress,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/{$task->id}/sprint", [
        'sprint_id' => $secondSprint->id,
    ])->assertOk()
        ->assertJsonPath('data.sprint_id', $secondSprint->id)
        ->assertJsonPath('data.status', TaskStatus::InProgress->value);
});

it('returns a task to the backlog when sprint id is null', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create();
    $task = Task::factory()->for($project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Blocked,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/{$task->id}/sprint", [
        'sprint_id' => null,
    ])->assertOk()
        ->assertJsonPath('data.sprint_id', null)
        ->assertJsonPath('data.status', TaskStatus::Backlog->value);
});

it('rejects a sprint from another project or a missing sprint id', function () {
    $task = Task::factory()->create();
    $otherSprint = Sprint::factory()->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $url = "/api/projects/{$task->project_id}/tasks/{$task->id}/sprint";

    $this->patchJson($url, ['sprint_id' => $otherSprint->id])
        ->assertUnprocessable()
        ->assertInvalid(['sprint_id']);
    $this->patchJson($url, [])
        ->assertUnprocessable()
        ->assertInvalid(['sprint_id']);
});

it('returns 404 for a task requested through a different project', function () {
    $task = Task::factory()->create();
    $otherProject = Project::factory()->for($task->project->user)->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->patchJson("/api/projects/{$otherProject->id}/tasks/{$task->id}/sprint", ['sprint_id' => null])
        ->assertNotFound();
});

it('hides sprint assignment from another user', function () {
    $task = Task::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson("/api/projects/{$task->project_id}/tasks/{$task->id}/sprint", ['sprint_id' => null])
        ->assertNotFound();
});
