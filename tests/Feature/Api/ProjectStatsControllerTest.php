<?php

use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for project statistics', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/projects/{$project->id}/stats")->assertUnauthorized();
});

it('returns task point progress and active sprint statistics', function () {
    $project = Project::factory()->create();
    $activeSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Backlog, 'story_points' => 1]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'story_points' => 2]);
    Task::factory()->for($project)->create(['status' => TaskStatus::InProgress, 'story_points' => 3]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Blocked, 'story_points' => 5]);
    Task::factory()->for($project)->create([
        'sprint_id' => $activeSprint->id,
        'status' => TaskStatus::Done,
        'story_points' => 8,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/stats")
        ->assertOk()
        ->assertJsonPath('progress', 42)
        ->assertJsonPath('story_points.total', 19)
        ->assertJsonPath('story_points.completed', 8)
        ->assertJsonPath('tasks.total', 5)
        ->assertJsonPath('tasks.backlog', 1)
        ->assertJsonPath('tasks.todo', 1)
        ->assertJsonPath('tasks.in_progress', 1)
        ->assertJsonPath('tasks.blocked', 1)
        ->assertJsonPath('tasks.done', 1)
        ->assertJsonPath('active_sprint.id', $activeSprint->id)
        ->assertJsonPath('active_sprint.planned_points', 8)
        ->assertJsonPath('active_sprint.completed_points', 8);
});

it('returns null when the project has no active sprint', function () {
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/stats")
        ->assertOk()
        ->assertJsonPath('active_sprint', null);
});

it('hides project statistics from another user', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson("/api/projects/{$project->id}/stats")->assertNotFound();
});
