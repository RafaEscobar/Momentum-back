<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows authenticated users to list and create projects', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Project::class))->toBeTrue()
        ->and($user->can('create', Project::class))->toBeTrue();
});

it('allows the owner to perform the project ability', function (string $ability) {
    $project = Project::factory()->create();

    expect($project->user->can($ability, $project))->toBeTrue();
})->with(['view', 'update', 'delete']);

it('hides the project from another user for the project ability', function (string $ability) {
    $project = Project::factory()->create();
    $otherUser = User::factory()->create();

    $response = Gate::forUser($otherUser)->inspect($ability, $project);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
})->with(['view', 'update', 'delete']);

it('denies unsupported soft deletion abilities', function (string $ability) {
    $project = Project::factory()->create();

    expect($project->user->can($ability, $project))->toBeFalse();
})->with(['restore', 'forceDelete']);
