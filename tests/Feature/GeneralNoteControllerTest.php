<?php

use App\Models\GeneralNote;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for general note endpoints', function () {
    $note = GeneralNote::factory()->create();

    $this->getJson('/api/notes')->assertUnauthorized();
    $this->getJson("/api/notes/{$note->id}")->assertUnauthorized();
});

it('lists only the users general notes newest first without content', function () {
    $user = User::factory()->create();

    foreach (range(1, 16) as $index) {
        GeneralNote::factory()->for($user)->create([
            'title' => "General note {$index}",
            'content' => str_repeat('private ', 100),
            'created_at' => now()->subMinutes(16 - $index),
        ]);
    }

    GeneralNote::factory()->create(['title' => 'Foreign general note']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/notes')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('data.0.title', 'General note 16')
        ->assertJsonMissingPath('data.0.content')
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonMissing(['title' => 'Foreign general note']);
});

it('searches general notes by title and treats wildcards literally', function () {
    $user = User::factory()->create();
    $matchingNote = GeneralNote::factory()->for($user)->create(['title' => 'Release 100% checklist']);
    GeneralNote::factory()->for($user)->create(['title' => 'Release 100X checklist']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/notes?search='.urlencode('  100%  '))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingNote->id);
});

it('creates a general note for the authenticated user and preserves raw markdown', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $markdown = "# Ideas\n\n<script>alert('xss')</script>";
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/notes', [
        'user_id' => $otherUser->id,
        'title' => 'Ideas generales',
        'content' => $markdown,
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Ideas generales')
        ->assertJsonPath('data.content', $markdown)
        ->assertJsonMissingPath('data.user_id');

    $this->assertDatabaseHas('general_notes', [
        'user_id' => $user->id,
        'title' => 'Ideas generales',
        'content' => $markdown,
    ]);
});

it('validates general note filters title and content', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/notes?search='.str_repeat('a', 256))
        ->assertUnprocessable()
        ->assertInvalid(['search']);

    $this->postJson('/api/notes', [
        'title' => str_repeat('a', 256),
        'content' => str_repeat('a', 50001),
    ])->assertUnprocessable()->assertInvalid(['title', 'content']);
});

it('shows updates and deletes a general note', function () {
    $note = GeneralNote::factory()->create([
        'title' => 'Draft',
        'content' => '**Original**',
    ]);
    Sanctum::actingAs($note->user, ['*']);
    $url = "/api/notes/{$note->id}";

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.content', '**Original**');

    $this->patchJson($url, [
        'title' => 'Published',
        'content' => '_Updated_',
    ])->assertOk()
        ->assertJsonPath('data.title', 'Published')
        ->assertJsonPath('data.content', '_Updated_');

    $this->deleteJson($url)->assertNoContent();
    $this->assertModelMissing($note);
});

it('hides every general note action from another user', function () {
    $note = GeneralNote::factory()->create([
        'title' => 'ISOLATION-SECRET-GENERAL-NOTE',
        'content' => 'ISOLATION-SECRET-CONTENT',
    ]);
    $attacker = User::factory()->create();
    Sanctum::actingAs($attacker, ['*']);
    $url = "/api/notes/{$note->id}";

    $this->getJson($url)->assertNotFound()->assertDontSee('ISOLATION-SECRET', false);
    $this->putJson($url, ['title' => 'Stolen'])->assertNotFound();
    $this->patchJson($url, ['title' => 'Stolen'])->assertNotFound();
    $this->deleteJson($url)->assertNotFound();

    expect($note->fresh()->title)->toBe('ISOLATION-SECRET-GENERAL-NOTE');
});
