<?php

use App\Enums\TaskStatus;
use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('uses one login limit for normalized identity variants and returns rate limit headers', function () {
    $variants = [
        'rafa@example.com',
        ' RAFA@example.com ',
        'Rafa@Example.Com',
        'rafa@EXAMPLE.COM',
        'rAfa@example.com',
    ];

    foreach ($variants as $email) {
        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/login', [
        'email' => 'rafa@example.com',
        'password' => 'incorrect-password',
    ])->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertHeader('X-RateLimit-Limit', '5')
        ->assertHeader('X-RateLimit-Remaining', '0');
});

it('does not allow unicode identity variants to evade the login limit', function () {
    foreach (['rafa', 'rAfa', 'RAFA', 'rafa', 'Rafa'] as $localPart) {
        $this->postJson('/api/login', [
            'email' => str_replace('a', 'á', $localPart).'@example.com',
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/login', [
        'email' => 'rafa@example.com',
        'password' => 'incorrect-password',
    ])->assertTooManyRequests();
});

it('recovers after the login rate limit window', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/login', [
            'email' => 'window@example.com',
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/login', [
        'email' => 'window@example.com',
        'password' => 'incorrect-password',
    ])->assertTooManyRequests();

    $this->travel(61)->seconds();

    $this->postJson('/api/login', [
        'email' => 'window@example.com',
        'password' => 'incorrect-password',
    ])->assertUnprocessable();
});

it('isolates login identity limits by IP to prevent targeted account lockout', function () {
    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson('/api/login', [
                'email' => 'target@example.com',
                'password' => 'incorrect-password',
            ])->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
        ->postJson('/api/login', [
            'email' => 'target@example.com',
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
});

it('rate limits registration by IP without blocking a different IP', function () {
    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->postJson('/api/register', ['email' => "person{$attempt}@example.com"])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
        ->postJson('/api/register', ['email' => 'sixth@example.com'])
        ->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.21'])
        ->postJson('/api/register', ['email' => 'sixth@example.com'])
        ->assertUnprocessable();
});

it('applies a global API limit per authenticated user', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    foreach (range(1, 120) as $attempt) {
        $this->getJson('/api/user')->assertOk();
    }

    $this->getJson('/api/user')
        ->assertTooManyRequests()
        ->assertHeader('X-RateLimit-Limit', '120');
});

it('applies a lower limit to expensive reads and bulk writes', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    foreach (range(1, 30) as $attempt) {
        $this->getJson('/api/dashboard')->assertOk();
    }

    $this->getJson('/api/dashboard')->assertTooManyRequests();

    foreach (range(1, 20) as $attempt) {
        $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
            'tasks' => [],
        ])->assertUnprocessable();
    }

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => [],
    ])->assertTooManyRequests();
});

it('accepts maximum bulk payloads and rejects one additional item', function () {
    $project = Project::factory()->create();
    $tasks = Task::factory()->for($project)->count(200)->create();
    $task = $tasks->first();
    $checklistItems = ChecklistItem::factory()->for($task)->count(200)->create();
    $tags = Tag::factory()->for($project->user)->count(50)->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => $tasks->values()->map(fn (Task $task, int $index): array => [
            'id' => $task->id,
            'status' => TaskStatus::Todo->value,
            'position' => $index,
        ])->all(),
    ])->assertOk()->assertJsonCount(200, 'data');

    $this->patchJson("/api/projects/{$project->id}/tasks/reorder", [
        'tasks' => array_fill(0, 201, [
            'id' => $task->id,
            'status' => TaskStatus::Todo->value,
            'position' => 0,
        ]),
    ])->assertUnprocessable()->assertInvalid(['tasks']);

    $this->patchJson("/api/tasks/{$task->id}/checklist/reorder", [
        'items' => $checklistItems->values()->map(fn (ChecklistItem $item, int $index): array => [
            'id' => $item->id,
            'position' => $index,
        ])->all(),
    ])->assertOk()->assertJsonCount(200, 'data');

    $this->putJson("/api/tasks/{$task->id}/tags", [
        'tag_ids' => $tags->pluck('id')->all(),
    ])->assertOk()->assertJsonCount(50, 'data.tags');

    $this->putJson("/api/tasks/{$task->id}/tags", [
        'tag_ids' => [...$tags->pluck('id')->all(), $tags->first()->id],
    ])->assertUnprocessable()->assertInvalid(['tag_ids']);
});

it('rejects invalid and ambiguous pagination values', function (string $query): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/projects?'.$query)
        ->assertUnprocessable()
        ->assertInvalid(['page']);
})->with([
    'negative' => 'page=-1',
    'zero' => 'page=0',
    'huge' => 'page=10001',
    'non numeric' => 'page=abc',
    'array' => 'page[]=1',
    'repeated' => 'page=1&page=2',
]);

it('validates named pagination parameters used by search', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/search?q=roadmap&projects_page=1&projects_page=2')
        ->assertUnprocessable()
        ->assertInvalid(['projects_page']);
});

it('rejects request bodies over the configured application limit', function (): void {
    config()->set('security.max_request_body_kb', 1);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson('/api/projects', [
        'name' => 'Oversized request',
        'description' => str_repeat('x', 2048),
    ])->assertStatus(413)
        ->assertExactJson(['message' => 'The request body is too large.']);
});
