<?php

use App\Enums\ActivityType;
use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityService;

it('centralizes activity creation with project user and subject', function () {
    $task = Task::factory()->create();

    $activity = app(ActivityService::class)->log(
        project: $task->project,
        subject: $task,
        type: ActivityType::TaskCreated,
        description: 'Task created',
        metadata: ['source' => 'api'],
    );

    expect($activity->project->is($task->project))->toBeTrue()
        ->and($activity->user->is($task->project->user))->toBeTrue()
        ->and($activity->subject->is($task))->toBeTrue()
        ->and($activity->type)->toBe(ActivityType::TaskCreated)
        ->and($activity->metadata)->toBe(['source' => 'api']);
});

it('limits the number of metadata items', function () {
    $project = Project::factory()->create();

    expect(fn () => app(ActivityService::class)->log(
        project: $project,
        subject: null,
        type: ActivityType::ProjectUpdated,
        description: 'Project updated',
        metadata: array_fill(0, 11, true),
    ))->toThrow(InvalidArgumentException::class);
});

it('limits the encoded metadata size', function () {
    $project = Project::factory()->create();

    expect(fn () => app(ActivityService::class)->log(
        project: $project,
        subject: null,
        type: ActivityType::ProjectUpdated,
        description: 'Project updated',
        metadata: ['source' => str_repeat('a', 2048)],
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects unsupported metadata keys and nested values', function () {
    $project = Project::factory()->create();
    $service = app(ActivityService::class);

    expect(fn () => $service->log(
        project: $project,
        subject: null,
        type: ActivityType::ProjectUpdated,
        description: 'Project updated',
        metadata: ['email' => 'private@example.test'],
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->log(
            project: $project,
            subject: null,
            type: ActivityType::ProjectUpdated,
            description: 'Project updated',
            metadata: ['source' => ['api']],
        ))->toThrow(InvalidArgumentException::class);
});

it('rejects a subject from another project', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->create();

    expect(fn () => app(ActivityService::class)->log(
        project: $project,
        subject: $task,
        type: ActivityType::TaskCreated,
        description: 'Task created',
    ))->toThrow(InvalidArgumentException::class);
});
