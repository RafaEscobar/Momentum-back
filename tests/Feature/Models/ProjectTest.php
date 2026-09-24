<?php

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

it('creates a project with defaults and casts its domain values', function () {
    $user = User::factory()->create();

    $project = $user->projects()->create([
        'name' => 'Momentum',
        'start_date' => '2026-09-19',
        'target_date' => '2026-12-31',
    ]);

    $this->assertModelExists($project);
    $project->refresh();

    expect($project->status)->toBe(ProjectStatus::Active)
        ->and($project->priority)->toBe(ProjectPriority::Medium)
        ->and($project->color)->toBe('#6366F1')
        ->and($project->start_date->toDateString())->toBe('2026-09-19')
        ->and($project->target_date->toDateString())->toBe('2026-12-31')
        ->and($project->user->is($user))->toBeTrue();
});

it('exposes projects through the user relationship', function () {
    $project = Project::factory()->create();

    expect($project->user->projects()->whereKey($project)->exists())->toBeTrue();
});

it('deletes projects when their user is deleted', function () {
    $project = Project::factory()->create();

    $project->user->delete();

    $this->assertModelMissing($project);
});

test('project progress is zero until tasks are implemented', function () {
    expect((new Project)->progress())->toBe(0);
});

test('project progress handles empty totals and rounds percentages', function (
    int $completedStoryPoints,
    int $totalStoryPoints,
    int $expectedProgress,
) {
    expect(Project::calculateProgress($completedStoryPoints, $totalStoryPoints))
        ->toBe($expectedProgress);
})->with([
    'no story points' => [0, 0, 0],
    'partially completed' => [5, 8, 63],
    'fully completed' => [8, 8, 100],
]);
