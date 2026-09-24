<?php

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;

it('stores Markdown as raw text and returns it only as JSON data', function (string $payload) {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $projectResponse = $this->patchJson("/api/projects/{$project->id}", [
        'description' => $payload,
    ])->assertOk()->assertHeader('content-type', 'application/json');
    $taskResponse = $this->postJson("/api/projects/{$project->id}/tasks", [
        'title' => 'Unsafe task',
        'description' => $payload,
    ])->assertCreated()->assertHeader('content-type', 'application/json');
    $noteResponse = $this->postJson("/api/projects/{$project->id}/notes", [
        'title' => 'Unsafe note',
        'content' => $payload,
    ])->assertCreated()->assertHeader('content-type', 'application/json');

    $projectResponse->assertJsonPath('data.description', $payload);
    $taskResponse->assertJsonPath('data.description', $payload);
    $noteResponse->assertJsonPath('data.content', $payload);
    expect($project->refresh()->description)->toBe($payload)
        ->and(Task::findOrFail($taskResponse->json('data.id'))->description)->toBe($payload)
        ->and(ProjectNote::findOrFail($noteResponse->json('data.id'))->content)->toBe($payload);
})->with([
    'script' => '<script>alert(1)</script>',
    'event handler' => '<img src=x onerror=alert(1)>',
    'javascript URL' => '[click](javascript:alert(1))',
    'SVG' => '<svg><script>alert(1)</script></svg>',
    'encoded' => '&#x3C;script&#x3E;alert(1)&#x3C;/script&#x3E;',
]);

it('keeps stored XSS payloads inert in activity and search responses', function () {
    $payload = '<img src=x onerror=alert(1)> javascript:alert(1)';
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $taskId = $this->postJson("/api/projects/{$project->id}/tasks", [
        'title' => $payload,
        'description' => '<script>alert(1)</script>',
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/projects/{$project->id}/activities")
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('data.0.subject.id', $taskId)
        ->assertJsonPath('data.0.subject.label', $payload)
        ->assertJsonPath('data.0.description', 'Tarea creada: '.$payload);

    $this->getJson('/api/search?q='.urlencode('<script>alert(1)</script>'))
        ->assertOk()
        ->assertJsonMissing(['<script>alert(1)</script>']);
});

it('does not reflect invalid search input in validation errors', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $payload = '<script>alert("reflected")</script>';

    $this->getJson('/api/search?q='.urlencode(str_repeat($payload, 4)))
        ->assertUnprocessable()
        ->assertInvalid(['q'])
        ->assertDontSee($payload, false);
});

it('adds a restrictive content security policy to responses', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $response = $this->get('https://localhost/');

    $response->assertOk()->assertHeader('Content-Security-Policy');

    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'", "object-src 'none'", "frame-ancestors 'none'")
        ->not->toContain('unsafe-eval', 'unsafe-inline');
});
