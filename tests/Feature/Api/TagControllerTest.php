<?php

use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for tag endpoints', function () {
    $this->getJson('/api/tags')->assertUnauthorized();
});

it('lists only tags owned by the authenticated user', function () {
    $user = User::factory()->create();
    $secondTag = Tag::factory()->for($user)->create(['name' => 'Backend']);
    $firstTag = Tag::factory()->for($user)->create(['name' => 'API']);
    Tag::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/tags')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $firstTag->id)
        ->assertJsonPath('data.1.id', $secondTag->id);
});

it('creates a tag for the authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/tags', [
        'name' => 'Urgent',
        'color' => '#EF4444',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Urgent')
        ->assertJsonPath('data.color', '#EF4444')
        ->assertJsonMissingPath('data.user_id');

    $this->assertDatabaseHas('tags', ['user_id' => $user->id, 'name' => 'Urgent']);
});

it('validates tag lengths and color format', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/tags', [
        'name' => str_repeat('a', 51),
        'color' => 'red',
    ])->assertUnprocessable()->assertInvalid(['name', 'color']);
});

it('prevents duplicate tag names per user but allows them across users', function () {
    $user = User::factory()->create();
    Tag::factory()->for($user)->create(['name' => 'Backend']);
    Tag::factory()->create(['name' => 'Backend']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/tags', ['name' => 'Backend', 'color' => '#111827'])
        ->assertUnprocessable()
        ->assertInvalid(['name']);
});

it('updates and deletes an owned tag', function () {
    $tag = Tag::factory()->create();
    Sanctum::actingAs($tag->user, ['*']);

    $this->patchJson("/api/tags/{$tag->id}", [
        'name' => 'Updated',
        'color' => '#22C55E',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated');

    $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();
    $this->assertModelMissing($tag);
});

it('hides another users tags', function () {
    $tag = Tag::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson("/api/tags/{$tag->id}", ['name' => 'Forbidden'])->assertNotFound();
    $this->deleteJson("/api/tags/{$tag->id}")->assertNotFound();
});
