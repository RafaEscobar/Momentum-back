<?php

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;

it('combines every task filter scope', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create();
    $tag = Tag::factory()->for($project->user)->create();
    $matchingTask = Task::factory()->for($project)->create([
        'sprint_id' => $sprint->id,
        'title' => 'Prepare release',
        'description' => 'Deployment checklist',
        'status' => TaskStatus::InProgress,
        'priority' => ProjectPriority::High,
        'type' => TaskType::Bug,
    ]);
    $matchingTask->tags()->attach($tag);
    Task::factory()->for($project)->create([
        'sprint_id' => $sprint->id,
        'title' => 'Prepare release',
        'status' => TaskStatus::Todo,
        'priority' => ProjectPriority::High,
        'type' => TaskType::Bug,
    ]);

    $tasks = $project->tasks()->filter([
        'status' => TaskStatus::InProgress->value,
        'priority' => ProjectPriority::High->value,
        'type' => TaskType::Bug->value,
        'sprint_id' => $sprint->id,
        'tag_id' => $tag->id,
        'search' => 'checklist',
    ])->get();

    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->is($matchingTask))->toBeTrue();
});

it('applies combined task filters through the paginated endpoint', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create();
    $tag = Tag::factory()->for($project->user)->create();
    $matchingTask = Task::factory()->for($project)->create([
        'sprint_id' => $sprint->id,
        'title' => 'Fix media search',
        'status' => TaskStatus::InProgress,
        'priority' => ProjectPriority::Critical,
        'type' => TaskType::Bug,
    ]);
    $matchingTask->tags()->attach($tag);
    Task::factory()->for($project)->create();
    Sanctum::actingAs($project->user, ['*']);

    $query = http_build_query([
        'status' => TaskStatus::InProgress->value,
        'priority' => ProjectPriority::Critical->value,
        'type' => TaskType::Bug->value,
        'sprint_id' => $sprint->id,
        'tag_id' => $tag->id,
        'search' => 'media',
    ]);

    $this->getJson("/api/projects/{$project->id}/tasks?{$query}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingTask->id)
        ->assertJsonPath('meta.per_page', 15);
});

it('rejects invalid filters and foreign sprint or tag ids', function () {
    $project = Project::factory()->create();
    $foreignSprint = Sprint::factory()->create();
    $foreignTag = Tag::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $query = http_build_query([
        'status' => 'review',
        'priority' => 'urgent',
        'type' => 'feature',
        'sprint_id' => $foreignSprint->id,
        'tag_id' => $foreignTag->id,
    ]);

    $this->getJson("/api/projects/{$project->id}/tasks?{$query}")
        ->assertUnprocessable()
        ->assertInvalid(['status', 'priority', 'type', 'sprint_id', 'tag_id']);
});
