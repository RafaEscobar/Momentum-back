<?php

use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('loads activity subjects with a fixed query count', function () {
    $task = Task::factory()->create();
    Activity::factory()->count(20)->create([
        'user_id' => $task->project->user_id,
        'project_id' => $task->project_id,
        'subject_type' => $task->getMorphClass(),
        'subject_id' => $task->id,
    ]);
    Sanctum::actingAs($task->project->user, ['*']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson("/api/projects/{$task->project_id}/activities")->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(4);
});

it('eager loads board tags and checklist aggregates with a fixed query count', function () {
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $task = Task::factory()->for($project)->create();
    $tags = Tag::factory()->for($project->user)->count(20)->create();
    $task->tags()->attach($tags);
    Sanctum::actingAs($project->user, ['*']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson("/api/projects/{$project->id}/board")->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(4);
});

it('loads dashboard aggregates and polymorphic activity without per-record queries', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $task = Task::factory()->for($project)->create();
    Activity::factory()->count(10)->create([
        'user_id' => $project->user_id,
        'project_id' => $project->id,
        'subject_type' => $task->getMorphClass(),
        'subject_id' => $task->id,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/dashboard')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(5);
});
