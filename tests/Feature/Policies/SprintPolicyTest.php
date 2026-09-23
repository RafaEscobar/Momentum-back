<?php

use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows project owners to use sprint abilities', function () {
    $sprint = Sprint::factory()->create();
    $user = $sprint->project->user;

    expect(Gate::forUser($user)->allows('viewAny', [Sprint::class, $sprint->project]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', [Sprint::class, $sprint->project]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $sprint))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $sprint))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $sprint))->toBeTrue();
});

it('denies sprint abilities to users who do not own the project', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create();
    $otherUser = User::factory()->create();

    expect(Gate::forUser($otherUser)->allows('viewAny', [Sprint::class, $project]))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('create', [Sprint::class, $project]))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('view', $sprint))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('update', $sprint))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('delete', $sprint))->toBeFalse();
});
