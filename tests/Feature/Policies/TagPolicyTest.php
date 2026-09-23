<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows owners to update and delete tags', function () {
    $tag = Tag::factory()->create();

    expect(Gate::forUser($tag->user)->allows('update', $tag))->toBeTrue()
        ->and(Gate::forUser($tag->user)->allows('delete', $tag))->toBeTrue();
});

it('denies tag abilities to other users', function () {
    $tag = Tag::factory()->create();
    $otherUser = User::factory()->create();

    expect(Gate::forUser($otherUser)->allows('update', $tag))->toBeFalse()
        ->and(Gate::forUser($otherUser)->allows('delete', $tag))->toBeFalse();
});
