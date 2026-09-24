<?php

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for sprint endpoints', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/projects/{$project->id}/sprints")->assertUnauthorized();
});

it('lists only project sprints with filters and pagination', function () {
    $project = Project::factory()->create();
    $matchingSprint = Sprint::factory()->for($project)->create([
        'name' => 'Release Sprint',
        'goal' => 'A long internal goal',
        'status' => SprintStatus::Active,
    ]);
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    Sprint::factory()->create(['status' => SprintStatus::Active]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/sprints?status=active&search=Release")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingSprint->id)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonMissingPath('data.0.goal');
});

it('creates a sprint for its project', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/sprints", [
        'name' => 'Sprint 1',
        'goal' => 'Ship the task workflow',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-14',
    ])->assertCreated()
        ->assertJsonPath('data.project_id', $project->id)
        ->assertJsonPath('data.name', 'Sprint 1')
        ->assertJsonPath('data.status', SprintStatus::Planned->value);

    $this->assertDatabaseHas('sprints', [
        'project_id' => $project->id,
        'name' => 'Sprint 1',
    ]);
});

it('validates sprint data and unique names within a project', function () {
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['name' => 'Sprint 1']);
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/sprints", [
        'name' => 'Sprint 1',
        'goal' => str_repeat('a', 10001),
        'start_date' => '2026-10-14',
        'end_date' => '2026-10-01',
        'status' => 'running',
    ])->assertUnprocessable()
        ->assertInvalid(['name', 'goal', 'end_date', 'status']);
});

it('allows the same sprint name in different projects', function () {
    $project = Project::factory()->create();
    Sprint::factory()->create(['name' => 'Sprint 1']);
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/sprints", ['name' => 'Sprint 1'])
        ->assertCreated();
});

it('shows and partially updates a sprint', function () {
    $sprint = Sprint::factory()->create(['name' => 'Old sprint']);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->getJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}")
        ->assertOk()
        ->assertJsonPath('data.goal', $sprint->goal);

    $this->patchJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}", [
        'name' => 'Updated sprint',
        'goal' => 'Updated goal',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Updated sprint')
        ->assertJsonPath('data.goal', 'Updated goal');
});

it('returns 404 for a sprint requested through a different project', function () {
    $sprint = Sprint::factory()->create();
    $otherProject = Project::factory()->for($sprint->project->user)->create();
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->getJson("/api/projects/{$otherProject->id}/sprints/{$sprint->id}")->assertNotFound();
    $this->patchJson("/api/projects/{$otherProject->id}/sprints/{$sprint->id}", ['name' => 'Moved'])->assertNotFound();
    $this->deleteJson("/api/projects/{$otherProject->id}/sprints/{$sprint->id}")->assertNotFound();
});

it('hides sprint endpoints for another users project', function () {
    $sprint = Sprint::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson("/api/projects/{$sprint->project_id}/sprints")->assertNotFound();
    $this->postJson("/api/projects/{$sprint->project_id}/sprints", ['name' => 'Forbidden'])->assertNotFound();
    $this->getJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}")->assertNotFound();
    $this->patchJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}", ['name' => 'Forbidden'])->assertNotFound();
    $this->deleteJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}")->assertNotFound();
});

it('deletes a sprint', function () {
    $sprint = Sprint::factory()->create();
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->deleteJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}")->assertNoContent();

    $this->assertDatabaseMissing('sprints', ['id' => $sprint->id]);
});

it('returns 422 and rolls back changes when another sprint is active', function () {
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $plannedSprint = Sprint::factory()->for($project)->create([
        'name' => 'Original name',
        'status' => SprintStatus::Planned,
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->patchJson("/api/projects/{$project->id}/sprints/{$plannedSprint->id}", [
        'name' => 'Should roll back',
        'status' => SprintStatus::Active->value,
    ])->assertUnprocessable()
        ->assertInvalid(['status']);

    expect($plannedSprint->refresh()->name)->toBe('Original name')
        ->and($plannedSprint->status)->toBe(SprintStatus::Planned);
});
