<?php

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/projects/{$project->id}/activities")->assertUnauthorized();
});

it('lists project activities newest first with pagination and subject summaries', function () {
    $task = Task::factory()->create(['title' => 'Prepare release']);
    $project = $task->project;

    foreach (range(1, 16) as $index) {
        Activity::factory()->create([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'subject_type' => $index === 16 ? $task->getMorphClass() : null,
            'subject_id' => $index === 16 ? $task->id : null,
            'created_at' => now()->subMinutes(16 - $index),
        ]);
    }

    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/activities")
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('data.0.subject.type', 'task')
        ->assertJsonPath('data.0.subject.label', 'Prepare release')
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.per_page', 15);
});

it('filters activities by type and inclusive date range', function () {
    $project = Project::factory()->create();
    $matching = Activity::factory()->create([
        'user_id' => $project->user_id,
        'project_id' => $project->id,
        'type' => ActivityType::TaskCompleted,
        'created_at' => '2026-09-15 23:59:59',
    ]);
    Activity::factory()->create([
        'user_id' => $project->user_id,
        'project_id' => $project->id,
        'type' => ActivityType::TaskCreated,
        'created_at' => '2026-09-15 12:00:00',
    ]);
    Activity::factory()->create([
        'user_id' => $project->user_id,
        'project_id' => $project->id,
        'type' => ActivityType::TaskCompleted,
        'created_at' => '2026-09-16 00:00:00',
    ]);
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/activities?type=task_completed&date_from=2026-09-15&date_to=2026-09-15")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});

it('validates activity filters', function () {
    $project = Project::factory()->create();
    Sanctum::actingAs($project->user, ['*']);

    $this->getJson("/api/projects/{$project->id}/activities?type=unknown&date_from=2026-09-20&date_to=2026-09-19")
        ->assertUnprocessable()
        ->assertInvalid(['type', 'date_to']);
});

it('hides another users project activities', function () {
    $project = Project::factory()->create();
    Activity::factory()->create([
        'user_id' => $project->user_id,
        'project_id' => $project->id,
    ]);
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson("/api/projects/{$project->id}/activities")->assertNotFound();
});
