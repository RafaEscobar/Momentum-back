<?php

use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
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
    $memoryBefore = memory_get_usage(true);
    $this->getJson("/api/projects/{$project->id}/board")->assertOk();
    $queryCount = count(DB::getQueryLog());
    $memoryIncrease = max(0, memory_get_usage(true) - $memoryBefore);
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(4)
        ->and($memoryIncrease)->toBeLessThan(16 * 1024 * 1024);
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
    $memoryBefore = memory_get_usage(true);
    $this->getJson('/api/dashboard')->assertOk();
    $queryCount = count(DB::getQueryLog());
    $memoryIncrease = max(0, memory_get_usage(true) - $memoryBefore);
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(5)
        ->and($memoryIncrease)->toBeLessThan(16 * 1024 * 1024);
});

it('keeps search queries and memory bounded with a larger result set', function () {
    $user = User::factory()->create();
    $projects = Project::factory()->for($user)->count(50)->create([
        'name' => 'Volume needle',
    ]);

    foreach ($projects as $project) {
        Task::factory()->for($project)->create(['title' => 'Volume needle']);
        ProjectNote::factory()->for($project)->create(['title' => 'Volume needle']);
    }

    Sanctum::actingAs($user, ['*']);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $memoryBefore = memory_get_usage(true);

    $this->getJson('/api/search?q=needle')->assertOk();

    $queryCount = count(DB::getQueryLog());
    $memoryIncrease = max(0, memory_get_usage(true) - $memoryBefore);
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(9)
        ->and($memoryIncrease)->toBeLessThan(16 * 1024 * 1024);
});
