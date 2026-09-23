<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskStatisticsService;
use Illuminate\Support\Facades\DB;

it('calculates project task and story point statistics with one aggregate query', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['status' => TaskStatus::Backlog, 'story_points' => 1]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'story_points' => 2]);
    Task::factory()->for($project)->create(['status' => TaskStatus::InProgress, 'story_points' => 3]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Blocked, 'story_points' => 5]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Done, 'story_points' => 8]);
    Task::factory()->create(['status' => TaskStatus::Done, 'story_points' => 13]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $statistics = app(TaskStatisticsService::class)->forProject($project);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($statistics)->toMatchArray([
        'total' => 5,
        'backlog' => 1,
        'todo' => 1,
        'in_progress' => 1,
        'blocked' => 1,
        'done' => 1,
        'pending' => 3,
        'total_story_points' => 19,
        'completed_story_points' => 8,
        'progress' => 42,
    ])->and($queries)->toHaveCount(1);
});

it('returns zeroed statistics when there are no tasks', function () {
    $project = Project::factory()->create();

    expect(app(TaskStatisticsService::class)->forProject($project))->toMatchArray([
        'total' => 0,
        'pending' => 0,
        'total_story_points' => 0,
        'completed_story_points' => 0,
        'progress' => 0,
    ]);
});
