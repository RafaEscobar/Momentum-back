<?php

use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows project owners to use note abilities', function () {
    $note = ProjectNote::factory()->create();
    $user = $note->project->user;

    expect(Gate::forUser($user)->allows('viewAny', [ProjectNote::class, $note->project]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('create', [ProjectNote::class, $note->project]))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $note))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $note))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $note))->toBeTrue();
});

it('denies note abilities to users who do not own the project', function () {
    $note = ProjectNote::factory()->create();
    $otherUser = User::factory()->create();

    expect(Gate::forUser($otherUser)->allows('viewAny', [ProjectNote::class, $note->project]))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('create', [ProjectNote::class, $note->project]))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('view', $note))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('update', $note))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('delete', $note))->toBeFalse();
});
