<?php

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for the backlog', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/projects/{$project->id}/backlog")->assertUnauthorized();
});

it('returns only tasks without a sprint ordered by position with total story points', function () {
    $project = Project::factory()->create();
    $secondTask = Task::factory()->for($project)->create([
        'sprint_id' => null,
        'position' => 2,
        'story_points' => 5,
        'status' => TaskStatus::Todo,
    ]);
    $firstTask = Task::factory()->for($project)->create([
        'sprint_id' => null,
        'position' => 1,
        'story_points' => 3,
    ]);
    $sprint = Sprint::factory()->for($project)->create();
    Task::factory()->for($project)->create(['sprint_id' => $sprint->id, 'story_points' => 13]);
    Task::factory()->create(['sprint_id' => null, 'story_points' => 8]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/backlog")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $firstTask->id)
        ->assertJsonPath('data.1.id', $secondTask->id)
        ->assertJsonPath('meta.story_points_total', 8);
});

it('filters backlog tasks by priority type and search', function () {
    $project = Project::factory()->create();
    $matchingTask = Task::factory()->for($project)->create([
        'title' => 'Critical login defect',
        'priority' => ProjectPriority::Critical,
        'type' => TaskType::Bug,
        'story_points' => 5,
    ]);
    Task::factory()->for($project)->create([
        'title' => 'Critical login improvement',
        'priority' => ProjectPriority::Critical,
        'type' => TaskType::Improvement,
        'story_points' => 8,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/backlog?priority=critical&type=bug&search=login")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingTask->id)
        ->assertJsonPath('meta.story_points_total', 5);
});

it('filters backlog tasks by tag ids when the pivot exists', function () {
    $project = Project::factory()->create();
    $tag = Tag::factory()->for($project->user)->create();
    $matchingTask = Task::factory()->for($project)->create();
    Task::factory()->for($project)->create();
    $matchingTask->tags()->attach($tag);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/backlog?tag_ids[]={$tag->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingTask->id);
});

it('validates backlog filters', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/backlog?priority=urgent&type=feature&tag_ids[]=0")
        ->assertUnprocessable()
        ->assertInvalid(['priority', 'type', 'tag_ids.0']);
});

it('hides another users backlog', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson("/api/projects/{$project->id}/backlog")->assertNotFound();
});
