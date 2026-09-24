<?php

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;

it('belongs to its user and project and casts metadata', function () {
    $project = Project::factory()->create();

    $activity = $project->activities()->create([
        'user_id' => $project->user_id,
        'type' => 'project_created',
        'description' => 'Project created',
        'metadata' => ['source' => 'api'],
    ])->refresh();

    expect($activity->user->is($project->user))->toBeTrue()
        ->and($activity->project->is($project))->toBeTrue()
        ->and($activity->metadata)->toBe(['source' => 'api'])
        ->and($activity->created_at)->not->toBeNull()
        ->and($activity->updated_at)->toBeNull();
});

it('may reference a polymorphic subject', function () {
    $task = Task::factory()->create();
    $activity = Activity::factory()->create([
        'user_id' => $task->project->user_id,
        'project_id' => $task->project_id,
        'type' => 'task_created',
        'description' => 'Task created',
    ]);

    $activity->subject()->associate($task);
    $activity->save();

    expect($activity->refresh()->subject->is($task))->toBeTrue();
});

it('allows activities without a subject', function () {
    $activity = Activity::factory()->create();

    expect($activity->subject_type)->toBeNull()
        ->and($activity->subject_id)->toBeNull()
        ->and($activity->subject)->toBeNull();
});

it('exposes activities through user and project relationships', function () {
    $activity = Activity::factory()->create();

    expect($activity->user->activities()->whereKey($activity)->exists())->toBeTrue()
        ->and($activity->project->activities()->whereKey($activity)->exists())->toBeTrue();
});

it('deletes activities when their project is deleted', function () {
    $activity = Activity::factory()->create();

    $activity->project->delete();

    $this->assertModelMissing($activity);
});
