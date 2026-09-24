<?php

use App\Enums\TaskStatus;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('calculates planned completed and progress points with SQL aggregates', function () {
    $sprint = Sprint::factory()->create();
    Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Done,
        'story_points' => 5,
    ]);
    Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::InProgress,
        'story_points' => 3,
    ]);
    Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Done,
        'story_points' => null,
    ]);

    $sprint = Sprint::query()->withPointTotals()->findOrFail($sprint->id);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $plannedPoints = $sprint->plannedPoints();
    $completedPoints = $sprint->completedPoints();
    $progress = $sprint->progressPercentage();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($plannedPoints)->toBe(8)
        ->and($completedPoints)->toBe(5)
        ->and($progress)->toBe(63)
        ->and($queries)->toBeEmpty();
});

it('returns zero progress when a sprint has no estimated points', function () {
    $sprint = Sprint::factory()->create()->freshWithPointTotals();

    expect($sprint->plannedPoints())->toBe(0)
        ->and($sprint->completedPoints())->toBe(0)
        ->and($sprint->progressPercentage())->toBe(0);
});

it('exposes point totals in sprint API resources', function () {
    $sprint = Sprint::factory()->create();
    Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Done,
        'story_points' => 8,
    ]);
    Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Todo,
        'story_points' => 5,
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->getJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}")
        ->assertOk()
        ->assertJsonPath('data.planned_points', 13)
        ->assertJsonPath('data.completed_points', 8)
        ->assertJsonPath('data.progress_percentage', 62);
});
