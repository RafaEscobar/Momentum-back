<?php

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;

it('creates a planned sprint with defaults and domain casts', function () {
    $project = Project::factory()->create();

    $sprint = $project->sprints()->create(['name' => 'Sprint 1'])->refresh();

    $this->assertModelExists($sprint);
    expect($sprint->status)->toBe(SprintStatus::Planned)
        ->and($sprint->goal)->toBeNull()
        ->and($sprint->start_date)->toBeNull()
        ->and($sprint->end_date)->toBeNull()
        ->and($sprint->completed_at)->toBeNull()
        ->and($sprint->project->is($project))->toBeTrue();
});

it('casts sprint dates and completion timestamp', function () {
    $sprint = Sprint::factory()->create([
        'status' => SprintStatus::Completed,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-14',
        'completed_at' => '2026-09-14 18:30:00',
    ]);

    expect($sprint->start_date->toDateString())->toBe('2026-09-01')
        ->and($sprint->end_date->toDateString())->toBe('2026-09-14')
        ->and($sprint->completed_at->toDateTimeString())->toBe('2026-09-14 18:30:00');
});

it('exposes sprint tasks through both relationships', function () {
    $sprint = Sprint::factory()->create();
    $task = Task::factory()->for($sprint->project)->create(['sprint_id' => $sprint->id]);

    expect($sprint->tasks()->whereKey($task)->exists())->toBeTrue()
        ->and($task->sprint->is($sprint))->toBeTrue();
});

it('returns tasks to the backlog when their sprint is deleted', function () {
    $sprint = Sprint::factory()->create();
    $task = Task::factory()->for($sprint->project)->create(['sprint_id' => $sprint->id]);

    $sprint->delete();

    expect($task->refresh()->sprint_id)->toBeNull();
});

it('deletes sprints when their project is deleted', function () {
    $sprint = Sprint::factory()->create();

    $sprint->project->delete();

    $this->assertModelMissing($sprint);
});
