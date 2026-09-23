<?php

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for task endpoints', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/projects/{$project->id}/tasks")->assertUnauthorized();
});

it('lists only tasks from the requested project in stable position order', function () {
    $project = Project::factory()->create();
    $laterTask = Task::factory()->for($project)->create(['position' => 2]);
    $firstTask = Task::factory()->for($project)->create(['position' => 1]);
    Task::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/tasks")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $firstTask->id)
        ->assertJsonPath('data.1.id', $laterTask->id)
        ->assertJsonPath('meta.per_page', 15);
});

it('searches tasks by title and validates the search length', function () {
    $project = Project::factory()->create();
    $matchingTask = Task::factory()->for($project)->create(['title' => 'Prepare release notes']);
    Task::factory()->for($project)->create(['title' => 'Fix login']);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/tasks?search=release")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingTask->id);

    $this->getJson("/api/projects/{$project->id}/tasks?search=".str_repeat('a', 256))
        ->assertUnprocessable()
        ->assertInvalid(['search']);
});

it('creates a task under its project with safe defaults', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $response = $this->postJson("/api/projects/{$project->id}/tasks", [
        'project_id' => $otherProject->id,
        'title' => 'Build task API',
        'description' => 'Expose the nested task endpoints.',
        'story_points' => 5,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.project_id', $project->id)
        ->assertJsonPath('data.type', TaskType::Task->value)
        ->assertJsonPath('data.priority', ProjectPriority::Medium->value)
        ->assertJsonPath('data.status', TaskStatus::Backlog->value);
    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'title' => 'Build task API',
        'story_points' => 5,
    ]);
});

it('validates task fields and rejects a sprint before the sprint module exists', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/tasks", [
        'title' => '',
        'description' => str_repeat('a', 10001),
        'type' => 'feature',
        'priority' => 'urgent',
        'status' => 'started',
        'story_points' => 4,
        'sprint_id' => 999,
    ])->assertUnprocessable()->assertInvalid([
        'title', 'description', 'type', 'priority', 'status', 'story_points', 'sprint_id',
    ]);
});

it('shows and updates a task from its parent project', function () {
    $task = Task::factory()->create(['title' => 'Old title']);
    Sanctum::actingAs($task->project->user, ['*']);

    $this->getJson("/api/projects/{$task->project_id}/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.description', $task->description);

    $this->putJson("/api/projects/{$task->project_id}/tasks/{$task->id}", [
        'title' => 'Updated title',
        'type' => TaskType::Bug->value,
        'priority' => ProjectPriority::High->value,
        'status' => TaskStatus::InProgress->value,
        'story_points' => 8,
    ])->assertOk()
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.status', TaskStatus::InProgress->value);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Updated title',
        'story_points' => 8,
    ]);
});

it('returns 404 for tasks requested through a different project', function () {
    $task = Task::factory()->create();
    $otherProject = Project::factory()->for($task->project->user)->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->getJson("/api/projects/{$otherProject->id}/tasks/{$task->id}")->assertNotFound();
    $this->patchJson("/api/projects/{$otherProject->id}/tasks/{$task->id}", ['title' => 'Moved'])->assertNotFound();
    $this->deleteJson("/api/projects/{$otherProject->id}/tasks/{$task->id}")->assertNotFound();
});

it('hides task endpoints for projects owned by another user', function () {
    $task = Task::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson("/api/projects/{$task->project_id}/tasks")->assertNotFound();
    $this->postJson("/api/projects/{$task->project_id}/tasks", ['title' => 'Forbidden'])->assertNotFound();
    $this->getJson("/api/projects/{$task->project_id}/tasks/{$task->id}")->assertNotFound();
    $this->patchJson("/api/projects/{$task->project_id}/tasks/{$task->id}", ['title' => 'Forbidden'])->assertNotFound();
    $this->deleteJson("/api/projects/{$task->project_id}/tasks/{$task->id}")->assertNotFound();
});

it('deletes a task from its project', function () {
    $task = Task::factory()->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->deleteJson("/api/projects/{$task->project_id}/tasks/{$task->id}")->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});
