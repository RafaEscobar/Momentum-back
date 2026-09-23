<?php

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

it('requires authentication', function () {
    $this->getJson('/api/search?q=chapter')->assertUnauthorized();
});

it('searches the users projects tasks and notes without long fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'name' => 'Chapter launch',
        'description' => str_repeat('private project ', 100),
    ]);
    $task = Task::factory()->for($project)->create([
        'title' => 'Review roadmap',
        'description' => 'Chapter details are here '.str_repeat('private ', 100),
    ]);
    $note = ProjectNote::factory()->for($project)->create([
        'title' => 'Meeting notes',
        'content' => '# Chapter decisions '.str_repeat('private ', 100),
    ]);
    $otherProject = Project::factory()->create(['name' => 'Chapter secret']);
    Task::factory()->for($otherProject)->create(['title' => 'Chapter secret task']);
    ProjectNote::factory()->for($otherProject)->create(['title' => 'Chapter secret note']);
    RateLimiter::clear(md5('search'.$user->id));
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/search?q=chapter')
        ->assertOk()
        ->assertJsonCount(1, 'projects')
        ->assertJsonCount(1, 'tasks')
        ->assertJsonCount(1, 'notes')
        ->assertJsonPath('projects.0.id', $project->id)
        ->assertJsonPath('tasks.0.id', $task->id)
        ->assertJsonPath('notes.0.id', $note->id)
        ->assertJsonMissingPath('projects.0.description')
        ->assertJsonMissingPath('tasks.0.description')
        ->assertJsonMissingPath('notes.0.content')
        ->assertJsonMissing(['Chapter secret']);
});

it('limits results to ten items per type', function () {
    $user = User::factory()->create();

    foreach (range(1, 11) as $index) {
        $project = Project::factory()->for($user)->create(['name' => "Needle project {$index}"]);
        Task::factory()->for($project)->create(['title' => "Needle task {$index}"]);
        ProjectNote::factory()->for($project)->create(['title' => "Needle note {$index}"]);
    }

    RateLimiter::clear(md5('search'.$user->id));
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/search?q=needle')
        ->assertOk()
        ->assertJsonCount(10, 'projects')
        ->assertJsonCount(10, 'tasks')
        ->assertJsonCount(10, 'notes')
        ->assertJsonPath('meta.projects.total', 11)
        ->assertJsonPath('meta.projects.last_page', 2)
        ->assertJsonPath('meta.tasks.total', 11)
        ->assertJsonPath('meta.notes.total', 11);

    $this->getJson('/api/search?q=needle&projects_page=2&tasks_page=2&notes_page=2')
        ->assertOk()
        ->assertJsonCount(1, 'projects')
        ->assertJsonCount(1, 'tasks')
        ->assertJsonCount(1, 'notes')
        ->assertJsonPath('meta.projects.current_page', 2)
        ->assertJsonPath('meta.tasks.current_page', 2)
        ->assertJsonPath('meta.notes.current_page', 2);
});

it('validates and trims the search query', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Release chapter']);
    RateLimiter::clear(md5('search'.$user->id));
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/search?q='.urlencode('  chapter  '))
        ->assertOk()
        ->assertJsonPath('projects.0.id', $project->id);
    $this->getJson('/api/search?q=a')->assertUnprocessable()->assertInvalid(['q']);
    $this->getJson('/api/search?q='.str_repeat('a', 101))->assertUnprocessable()->assertInvalid(['q']);
});

it('rate limits global search by authenticated user', function () {
    $user = User::factory()->create();
    RateLimiter::clear(md5('search'.$user->id));
    Sanctum::actingAs($user, ['*']);

    foreach (range(1, 30) as $attempt) {
        $this->getJson('/api/search?q=chapter')->assertOk();
    }

    $this->getJson('/api/search?q=chapter')->assertTooManyRequests();
});
