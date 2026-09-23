<?php

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for sprint actions', function () {
    $sprint = Sprint::factory()->create();

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")
        ->assertUnauthorized();
});

it('starts a planned sprint and records its start date', function () {
    $sprint = Sprint::factory()->create([
        'status' => SprintStatus::Planned,
        'start_date' => null,
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', SprintStatus::Active->value)
        ->assertJsonPath('data.start_date', today()->toDateString());
});

it('preserves an existing start date when starting a sprint', function () {
    $sprint = Sprint::factory()->create([
        'status' => SprintStatus::Planned,
        'start_date' => '2026-10-01',
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")
        ->assertOk()
        ->assertJsonPath('data.start_date', '2026-10-01');
});

it('rejects starting a sprint that is not planned', function () {
    $sprint = Sprint::factory()->create(['status' => SprintStatus::Completed]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")
        ->assertUnprocessable()
        ->assertInvalid(['status']);
});

it('rejects starting a second active sprint in the project', function () {
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $plannedSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/sprints/{$plannedSprint->id}/start")
        ->assertUnprocessable()
        ->assertInvalid(['status']);

    expect($plannedSprint->refresh()->status)->toBe(SprintStatus::Planned);
});

it('completes a sprint and records its completion timestamp', function () {
    $sprint = Sprint::factory()->create([
        'status' => SprintStatus::Active,
        'completed_at' => null,
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', SprintStatus::Completed->value)
        ->assertJsonPath('data.completed_at', fn (mixed $value): bool => is_string($value));

    expect($sprint->refresh()->completed_at)->not->toBeNull();
});

it('returns 404 for sprint actions through a different project', function () {
    $sprint = Sprint::factory()->create();
    $otherProject = Project::factory()->for($sprint->project->user)->create();
    Sanctum::actingAs($sprint->project->user, ['*']);

    $this->postJson("/api/projects/{$otherProject->id}/sprints/{$sprint->id}/start")->assertNotFound();
    $this->postJson("/api/projects/{$otherProject->id}/sprints/{$sprint->id}/complete")->assertNotFound();
});

it('hides sprint actions from another user', function () {
    $sprint = Sprint::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/start")->assertNotFound();
    $this->postJson("/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/complete")->assertNotFound();
});
