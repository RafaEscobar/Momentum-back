<?php

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for note endpoints', function () {
    $note = ProjectNote::factory()->create();

    $this->getJson("/api/projects/{$note->project_id}/notes")->assertUnauthorized();
});

it('lists only project notes newest first without full content', function () {
    $project = Project::factory()->create();

    foreach (range(1, 16) as $index) {
        ProjectNote::factory()->for($project)->create([
            'title' => "Note {$index}",
            'content' => str_repeat('private ', 100),
            'created_at' => now()->subMinutes(16 - $index),
        ]);
    }

    ProjectNote::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/notes")
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('data.0.title', 'Note 16')
        ->assertJsonMissingPath('data.0.content')
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.per_page', 15);
});

it('creates a note and preserves raw markdown', function () {
    $project = Project::factory()->create();
    $markdown = "# Release\n\n<script>alert('xss')</script>";
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/notes", [
        'project_id' => Project::factory()->create()->id,
        'title' => 'Release notes',
        'content' => $markdown,
    ])->assertCreated()
        ->assertJsonPath('data.project_id', $project->id)
        ->assertJsonPath('data.content', $markdown);

    $this->assertDatabaseHas('project_notes', [
        'project_id' => $project->id,
        'title' => 'Release notes',
        'content' => $markdown,
    ]);
});

it('validates note title and content length', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/notes", [
        'title' => str_repeat('a', 256),
        'content' => str_repeat('a', 50001),
    ])->assertUnprocessable()->assertInvalid(['title', 'content']);
});

it('shows updates and deletes a project note', function () {
    $note = ProjectNote::factory()->create([
        'title' => 'Draft',
        'content' => '**Original**',
    ]);
    Sanctum::actingAs($note->project->user, ['*']);
    $url = "/api/projects/{$note->project_id}/notes/{$note->id}";

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

it('returns 404 for a note requested through a different project', function () {
    $note = ProjectNote::factory()->create();
    $otherProject = Project::factory()->for($note->project->user)->create();
    Sanctum::actingAs($note->project->user, ['*']);

    $url = "/api/projects/{$otherProject->id}/notes/{$note->id}";

    $this->getJson($url)->assertNotFound();
    $this->patchJson($url, ['title' => 'Moved'])->assertNotFound();
    $this->deleteJson($url)->assertNotFound();
});

it('hides note endpoints from another user', function () {
    $note = ProjectNote::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $collectionUrl = "/api/projects/{$note->project_id}/notes";
    $memberUrl = "{$collectionUrl}/{$note->id}";

    $this->getJson($collectionUrl)->assertNotFound();
    $this->postJson($collectionUrl, ['title' => 'Hidden', 'content' => 'Hidden'])->assertNotFound();
    $this->getJson($memberUrl)->assertNotFound();
    $this->patchJson($memberUrl, ['title' => 'Hidden'])->assertNotFound();
    $this->deleteJson($memberUrl)->assertNotFound();
});
