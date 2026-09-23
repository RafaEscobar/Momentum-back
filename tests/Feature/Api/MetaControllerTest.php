<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for metadata endpoints', function () {
    $this->getJson('/api/meta/task-types')->assertUnauthorized();
});

it('returns task type options', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/meta/task-types')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['value' => 'story', 'label' => 'Story'],
            ['value' => 'task', 'label' => 'Task'],
            ['value' => 'bug', 'label' => 'Bug'],
            ['value' => 'improvement', 'label' => 'Improvement'],
        ]]);
});

it('returns task status options', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/meta/task-statuses')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['value' => 'backlog', 'label' => 'Backlog'],
            ['value' => 'todo', 'label' => 'Todo'],
            ['value' => 'in_progress', 'label' => 'In Progress'],
            ['value' => 'blocked', 'label' => 'Blocked'],
            ['value' => 'done', 'label' => 'Done'],
        ]]);
});

it('returns priority options', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/meta/priorities')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['value' => 'low', 'label' => 'Low'],
            ['value' => 'medium', 'label' => 'Medium'],
            ['value' => 'high', 'label' => 'High'],
            ['value' => 'critical', 'label' => 'Critical'],
        ]]);
});

it('returns story point options', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/meta/story-points')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['value' => 1, 'label' => '1'],
            ['value' => 2, 'label' => '2'],
            ['value' => 3, 'label' => '3'],
            ['value' => 5, 'label' => '5'],
            ['value' => 8, 'label' => '8'],
            ['value' => 13, 'label' => '13'],
        ]]);
});

it('returns project status options', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/meta/project-statuses')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'paused', 'label' => 'Paused'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'archived', 'label' => 'Archived'],
        ]]);
});
