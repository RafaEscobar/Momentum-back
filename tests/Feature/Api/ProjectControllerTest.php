<?php

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 401 when no token is provided', function () {
    $this->getJson('/api/projects')->assertUnauthorized();
});

it('lists only projects owned by the authenticated user', function () {
    $user = User::factory()->create();
    $ownedProject = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/projects');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $ownedProject->id)
        ->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('data.0.description')
        ->assertJsonMissing(['id' => $otherProject->id]);
});

it('filters projects by status and priority', function () {
    $user = User::factory()->create();
    $matchingProject = Project::factory()->for($user)->create([
        'status' => ProjectStatus::Active,
        'priority' => ProjectPriority::High,
    ]);
    Project::factory()->for($user)->create([
        'status' => ProjectStatus::Paused,
        'priority' => ProjectPriority::High,
    ]);
    Project::factory()->for($user)->create([
        'status' => ProjectStatus::Active,
        'priority' => ProjectPriority::Low,
    ]);
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/projects?status=active&priority=high');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingProject->id);
});

it('searches projects by name', function () {
    $user = User::factory()->create();
    $matchingProject = Project::factory()->for($user)->create(['name' => 'MyMediaList API']);
    Project::factory()->for($user)->create(['name' => 'Personal Finance']);
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/projects?search=MediaList');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingProject->id);
});

it('orders projects by newest creation date', function () {
    $user = User::factory()->create();
    $olderProject = Project::factory()->for($user)->create(['created_at' => now()->subDay()]);
    $newerProject = Project::factory()->for($user)->create(['created_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/projects');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $newerProject->id)
        ->assertJsonPath('data.1.id', $olderProject->id);
});

it('paginates project listings', function () {
    $user = User::factory()->create();
    Project::factory()->count(16)->for($user)->create();
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/projects');

    $response
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 16)
        ->assertJsonStructure(['links', 'meta']);
});

it('returns 422 when project filters are invalid', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/projects?status=unknown&priority=urgent');

    $response
        ->assertUnprocessable()
        ->assertInvalid(['status', 'priority']);
});

it('creates a project for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/projects', [
        'user_id' => $otherUser->id,
        'name' => 'Momentum API',
        'description' => 'Backend development',
        'icon' => 'code',
        'start_date' => '2026-09-19',
        'target_date' => '2026-12-31',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Momentum API')
        ->assertJsonPath('data.status', ProjectStatus::Active->value)
        ->assertJsonPath('data.priority', ProjectPriority::Medium->value)
        ->assertJsonPath('data.color', '#6366F1')
        ->assertJsonMissingPath('data.user_id');
    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'Momentum API',
        'priority' => ProjectPriority::Medium->value,
    ]);
});

it('returns 422 when project data is invalid', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/projects', [
        'name' => '',
        'status' => 'unknown',
        'priority' => 'urgent',
        'color' => 'blue',
        'start_date' => '2026-09-20',
        'target_date' => '2026-09-19',
    ]);

    $response
        ->assertUnprocessable()
        ->assertInvalid(['name', 'status', 'priority', 'color', 'target_date']);
    $this->assertDatabaseCount('projects', 0);
});

it('shows an owned project', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.progress', 0)
        ->assertJsonStructure(['data' => [
            'id',
            'name',
            'description',
            'status',
            'priority',
            'color',
            'icon',
            'start_date',
            'target_date',
            'progress',
            'created_at',
            'updated_at',
        ]]);
});

it('returns 404 when showing another users project', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson("/api/projects/{$project->id}")->assertNotFound();
});

it('partially updates an owned project', function () {
    $project = Project::factory()->create([
        'name' => 'Old name',
        'priority' => ProjectPriority::Low,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $response = $this->patchJson("/api/projects/{$project->id}", [
        'name' => 'New name',
        'priority' => ProjectPriority::Critical->value,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'New name')
        ->assertJsonPath('data.priority', ProjectPriority::Critical->value);
    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'New name',
        'priority' => ProjectPriority::Critical->value,
    ]);
});

it('returns 422 when a partial update makes the target date precede the start date', function () {
    $project = Project::factory()->create([
        'start_date' => '2026-09-19',
        'target_date' => '2026-12-31',
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $response = $this->patchJson("/api/projects/{$project->id}", [
        'target_date' => '2026-09-18',
    ]);

    $response->assertUnprocessable()->assertInvalid(['target_date']);
    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'target_date' => '2026-12-31',
    ]);
});

it('returns 404 when updating another users project', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson("/api/projects/{$project->id}", ['name' => 'Forbidden'])
        ->assertNotFound();
    $this->assertDatabaseMissing('projects', [
        'id' => $project->id,
        'name' => 'Forbidden',
    ]);
});

it('archives an owned project', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    Sanctum::actingAs($project->user, ['*']);

    $response = $this->patchJson("/api/projects/{$project->id}", [
        'status' => ProjectStatus::Archived->value,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.status', ProjectStatus::Archived->value);
    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'status' => ProjectStatus::Archived->value,
    ]);
});

it('deletes an owned project', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $response = $this->deleteJson("/api/projects/{$project->id}");

    $response->assertNoContent();
    $this->assertModelMissing($project);
});

it('returns 404 when deleting another users project', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->deleteJson("/api/projects/{$project->id}")->assertNotFound();

    $this->assertModelExists($project);
});
