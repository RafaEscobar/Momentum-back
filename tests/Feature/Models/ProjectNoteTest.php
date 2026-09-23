<?php

use App\Models\ProjectNote;

it('belongs to a project', function () {
    $note = ProjectNote::factory()->create();

    expect($note->project->notes()->whereKey($note)->exists())->toBeTrue();
});

it('stores markdown content without rendering it', function () {
    $markdown = "# Heading\n\n<script>alert('xss')</script>";
    $note = ProjectNote::factory()->create(['content' => $markdown]);

    expect($note->refresh()->content)->toBe($markdown);
});

it('deletes notes when their project is deleted', function () {
    $note = ProjectNote::factory()->create();

    $note->project->delete();

    $this->assertModelMissing($note);
});
