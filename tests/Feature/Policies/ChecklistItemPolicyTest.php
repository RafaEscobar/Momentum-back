<?php

use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows task owners to update and delete checklist items', function () {
    $item = ChecklistItem::factory()->create();
    $user = $item->task->project->user;

    expect(Gate::forUser($user)->allows('update', $item))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $item))->toBeTrue();
});

it('denies checklist item abilities to other users', function () {
    $item = ChecklistItem::factory()->create();
    $otherUser = User::factory()->create();

    expect(Gate::forUser($otherUser)->allows('update', $item))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('delete', $item))->toBeFalse();
});
