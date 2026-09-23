<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('calculates project progress from all existing task story points', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['status' => TaskStatus::Done, 'story_points' => 5]);
    Task::factory()->for($project)->create(['status' => TaskStatus::InProgress, 'story_points' => 3]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Done, 'story_points' => null]);
    $deletedTask = Task::factory()->for($project)->create(['status' => TaskStatus::Done, 'story_points' => 13]);
    $deletedTask->delete();

    $project = Project::query()->withTaskPointTotals()->findOrFail($project->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $totalPoints = $project->totalStoryPoints();
    $completedPoints = $project->completedStoryPoints();
    $progress = $project->progress();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($totalPoints)->toBe(8)
        ->and($completedPoints)->toBe(5)
        ->and($progress)->toBe(63)
        ->and($queries)->toBeEmpty();
});

it('returns zero progress when a project has no estimated points', function () {
    $project = Project::factory()->create()->freshWithTaskPointTotals();

    expect($project->totalStoryPoints())->toBe(0)
        ->and($project->completedStoryPoints())->toBe(0)
        ->and($project->progress())->toBe(0);
});

it('exposes consistent project progress in list and detail resources', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['status' => TaskStatus::Done, 'story_points' => 3]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'story_points' => 5]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertJsonPath('data.0.progress', 38);
    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.progress', 38);
});
