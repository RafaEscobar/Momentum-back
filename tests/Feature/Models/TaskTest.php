<?php

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;

it('creates a backlog task with defaults and domain casts', function () {
    $project = Project::factory()->create();

    $task = $project->tasks()->create(['title' => 'Create task module'])->refresh();

    $this->assertModelExists($task);
    expect($task->sprint_id)->toBeNull()
        ->and($task->type)->toBe(TaskType::Task)
        ->and($task->priority)->toBe(ProjectPriority::Medium)
        ->and($task->status)->toBe(TaskStatus::Backlog)
        ->and($task->story_points)->toBeNull()
        ->and($task->position)->toBe(0)
        ->and($task->completed_at)->toBeNull()
        ->and($task->project->is($project))->toBeTrue();
});

it('casts story points, position, and completion date', function () {
    $task = Task::factory()->create([
        'status' => TaskStatus::Done,
        'story_points' => 8,
        'position' => 3,
        'completed_at' => '2026-09-23 10:30:00',
    ]);

    expect($task->story_points)->toBe(8)
        ->and($task->position)->toBe(3)
        ->and($task->completed_at->toDateTimeString())->toBe('2026-09-23 10:30:00');
});

it('exposes tasks through the project relationship', function () {
    $task = Task::factory()->create();

    expect($task->project->tasks()->whereKey($task)->exists())->toBeTrue();
});

it('deletes tasks when their project is deleted', function () {
    $task = Task::factory()->create();

    $task->project->delete();

    $this->assertModelMissing($task);
});
