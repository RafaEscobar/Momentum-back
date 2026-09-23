<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows project owners to use task abilities', function () {
    $task = Task::factory()->create();
    $user = $task->project->user;

    expect(Gate::forUser($user)->allows('viewAny', [Task::class, $task->project]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', [Task::class, $task->project]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $task))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $task))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $task))->toBeTrue();
});

it('denies task abilities to users who do not own the project', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    $otherUser = User::factory()->create();

    expect(Gate::forUser($otherUser)->allows('viewAny', [Task::class, $project]))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('create', [Task::class, $project]))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('view', $task))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('update', $task))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('delete', $task))->toBeFalse();
});
