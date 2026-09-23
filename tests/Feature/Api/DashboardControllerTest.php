<?php

use App\Enums\ActivityType;
use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication for the dashboard', function () {
    $this->getJson('/api/dashboard')->assertUnauthorized();
});

it('returns the users active projects sprints summary and recent activity', function () {
    $user = User::factory()->create();
    $activeProject = Project::factory()->for($user)->create([
        'name' => 'Active project',
        'status' => ProjectStatus::Active,
    ]);
    $pausedProject = Project::factory()->for($user)->create(['status' => ProjectStatus::Paused]);
    $activeSprint = Sprint::factory()->for($activeProject)->create(['status' => SprintStatus::Active]);
    Sprint::factory()->for($pausedProject)->create(['status' => SprintStatus::Planned]);

    Task::factory()->for($activeProject)->create(['status' => TaskStatus::Backlog, 'story_points' => 2]);
    Task::factory()->for($activeProject)->create(['status' => TaskStatus::Todo, 'story_points' => 3]);
    Task::factory()->for($activeProject)->create(['status' => TaskStatus::InProgress, 'story_points' => 5]);
    Task::factory()->for($pausedProject)->create(['status' => TaskStatus::Blocked, 'story_points' => 1]);
    Task::factory()->for($activeProject)->create([
        'sprint_id' => $activeSprint->id,
        'status' => TaskStatus::Done,
        'story_points' => 8,
    ]);

    foreach (range(1, 11) as $index) {
        Activity::factory()->create([
            'user_id' => $user->id,
            'project_id' => $activeProject->id,
            'type' => ActivityType::TaskCreated,
            'description' => "Activity {$index}",
            'created_at' => now()->subMinutes(11 - $index),
        ]);
    }

    $otherProject = Project::factory()->create(['status' => ProjectStatus::Active]);
    Sprint::factory()->for($otherProject)->create(['status' => SprintStatus::Active]);
    Task::factory()->for($otherProject)->create(['status' => TaskStatus::Done, 'story_points' => 13]);
    Activity::factory()->create([
        'user_id' => $otherProject->user_id,
        'project_id' => $otherProject->id,
        'description' => 'Other user activity',
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonCount(1, 'projects')
        ->assertJsonPath('projects.0.id', $activeProject->id)
        ->assertJsonPath('projects.0.progress', 44)
        ->assertJsonCount(1, 'active_sprints')
        ->assertJsonPath('active_sprints.0.id', $activeSprint->id)
        ->assertJsonPath('active_sprints.0.planned_points', 8)
        ->assertJsonPath('active_sprints.0.completed_points', 8)
        ->assertJsonPath('summary.active_projects', 1)
        ->assertJsonPath('summary.planned_story_points', 19)
        ->assertJsonPath('summary.completed_story_points', 8)
        ->assertJsonPath('summary.pending_tasks', 3)
        ->assertJsonPath('summary.in_progress_tasks', 1)
        ->assertJsonPath('summary.completed_tasks', 1)
        ->assertJsonCount(10, 'recent_activity')
        ->assertJsonPath('recent_activity.0.description', 'Activity 11')
        ->assertJsonMissing(['Other user activity']);
});
