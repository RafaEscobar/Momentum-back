<?php

use App\Enums\SprintStatus;
use App\Models\Activity;
use App\Models\ChecklistItem;
use App\Models\GeneralNote;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ChecklistItemPolicy;
use App\Policies\GeneralNotePolicy;
use App\Policies\ProjectNotePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SprintPolicy;
use App\Policies\TagPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->resourceOwner = User::factory()->create();
    $this->attacker = User::factory()->create();
    $this->foreignProject = Project::factory()->for($this->resourceOwner)->create([
        'name' => 'ISOLATION-SECRET-PROJECT',
        'description' => 'ISOLATION-SECRET-DESCRIPTION',
    ]);
    $this->foreignTask = Task::factory()->for($this->foreignProject)->create([
        'title' => 'ISOLATION-SECRET-TASK',
        'description' => 'ISOLATION-SECRET-TASK-DESCRIPTION',
    ]);
    $this->foreignSprint = Sprint::factory()->for($this->foreignProject)->create([
        'name' => 'ISOLATION-SECRET-SPRINT',
        'status' => SprintStatus::Planned,
    ]);
    $this->foreignNote = ProjectNote::factory()->for($this->foreignProject)->create([
        'title' => 'ISOLATION-SECRET-NOTE',
        'content' => 'ISOLATION-SECRET-NOTE-CONTENT',
    ]);
    $this->foreignTag = Tag::factory()->for($this->resourceOwner)->create([
        'name' => 'ISOLATION-SECRET-TAG',
    ]);
    $this->foreignChecklistItem = ChecklistItem::factory()->for($this->foreignTask)->create([
        'title' => 'ISOLATION-SECRET-CHECKLIST',
    ]);
    $this->foreignActivity = Activity::factory()
        ->for($this->resourceOwner)
        ->for($this->foreignProject)
        ->create(['description' => 'ISOLATION-SECRET-ACTIVITY']);
});

it('requires Sanctum authentication on every non-public API route', function () {
    $publicRoutes = [
        'POST api/login',
        'POST api/register',
    ];

    $unprotectedRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'api/'))
        ->reject(function (IlluminateRoute $route) use ($publicRoutes): bool {
            return collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD')
                ->contains(fn (string $method): bool => in_array("{$method} {$route->uri()}", $publicRoutes, true));
        })
        ->reject(fn (IlluminateRoute $route): bool => in_array('auth:sanctum', $route->gatherMiddleware(), true))
        ->map(fn (IlluminateRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unprotectedRoutes)->toBe([]);
});

it('registers an ownership policy for every user-owned resource', function () {
    $expectedPolicies = [
        Project::class => ProjectPolicy::class,
        Task::class => TaskPolicy::class,
        Sprint::class => SprintPolicy::class,
        ChecklistItem::class => ChecklistItemPolicy::class,
        GeneralNote::class => GeneralNotePolicy::class,
        Tag::class => TagPolicy::class,
        ProjectNote::class => ProjectNotePolicy::class,
        Activity::class => ActivityPolicy::class,
    ];

    foreach ($expectedPolicies as $model => $policy) {
        expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
    }
});

it('hides every foreign resource action from another user', function (string $method, string $uri, array $payload = []) {
    Sanctum::actingAs($this->attacker, ['*']);

    $uri = strtr($uri, [
        '{project}' => (string) $this->foreignProject->getKey(),
        '{task}' => (string) $this->foreignTask->getKey(),
        '{sprint}' => (string) $this->foreignSprint->getKey(),
        '{note}' => (string) $this->foreignNote->getKey(),
        '{tag}' => (string) $this->foreignTag->getKey(),
        '{checklistItem}' => (string) $this->foreignChecklistItem->getKey(),
    ]);

    $this->json($method, $uri, $payload)
        ->assertNotFound()
        ->assertDontSee('ISOLATION-SECRET', false);
})->with([
    'project show' => ['GET', '/api/projects/{project}'],
    'project put' => ['PUT', '/api/projects/{project}', ['name' => 'Stolen']],
    'project patch' => ['PATCH', '/api/projects/{project}', ['name' => 'Stolen']],
    'project delete' => ['DELETE', '/api/projects/{project}'],
    'project activities' => ['GET', '/api/projects/{project}/activities'],
    'project backlog' => ['GET', '/api/projects/{project}/backlog'],
    'project board' => ['GET', '/api/projects/{project}/board'],
    'project stats' => ['GET', '/api/projects/{project}/stats'],
    'note index' => ['GET', '/api/projects/{project}/notes'],
    'note store' => ['POST', '/api/projects/{project}/notes', ['title' => 'Stolen', 'content' => 'Stolen']],
    'note show' => ['GET', '/api/projects/{project}/notes/{note}'],
    'note put' => ['PUT', '/api/projects/{project}/notes/{note}', ['title' => 'Stolen']],
    'note patch' => ['PATCH', '/api/projects/{project}/notes/{note}', ['title' => 'Stolen']],
    'note delete' => ['DELETE', '/api/projects/{project}/notes/{note}'],
    'task index' => ['GET', '/api/projects/{project}/tasks'],
    'task store' => ['POST', '/api/projects/{project}/tasks', ['title' => 'Stolen']],
    'task reorder' => ['PATCH', '/api/projects/{project}/tasks/reorder', ['tasks' => [['id' => 1, 'status' => 'todo', 'position' => 1]]]],
    'task show' => ['GET', '/api/projects/{project}/tasks/{task}'],
    'task put' => ['PUT', '/api/projects/{project}/tasks/{task}', ['title' => 'Stolen']],
    'task patch' => ['PATCH', '/api/projects/{project}/tasks/{task}', ['title' => 'Stolen']],
    'task delete' => ['DELETE', '/api/projects/{project}/tasks/{task}'],
    'task sprint' => ['PATCH', '/api/projects/{project}/tasks/{task}/sprint', ['sprint_id' => null]],
    'task status' => ['PATCH', '/api/projects/{project}/tasks/{task}/status', ['status' => 'done']],
    'sprint index' => ['GET', '/api/projects/{project}/sprints'],
    'sprint store' => ['POST', '/api/projects/{project}/sprints', ['name' => 'Stolen']],
    'sprint show' => ['GET', '/api/projects/{project}/sprints/{sprint}'],
    'sprint put' => ['PUT', '/api/projects/{project}/sprints/{sprint}', ['name' => 'Stolen']],
    'sprint patch' => ['PATCH', '/api/projects/{project}/sprints/{sprint}', ['name' => 'Stolen']],
    'sprint delete' => ['DELETE', '/api/projects/{project}/sprints/{sprint}'],
    'sprint start' => ['POST', '/api/projects/{project}/sprints/{sprint}/start'],
    'sprint complete' => ['POST', '/api/projects/{project}/sprints/{sprint}/complete', ['unfinished_action' => 'backlog']],
    'task tag sync' => ['PUT', '/api/tasks/{task}/tags', ['tag_ids' => []]],
    'checklist store' => ['POST', '/api/tasks/{task}/checklist', ['title' => 'Stolen']],
    'checklist reorder' => ['PATCH', '/api/tasks/{task}/checklist/reorder', ['items' => [['id' => 1, 'position' => 1]]]],
    'checklist update' => ['PATCH', '/api/tasks/{task}/checklist/{checklistItem}', ['title' => 'Stolen']],
    'checklist delete' => ['DELETE', '/api/tasks/{task}/checklist/{checklistItem}'],
    'tag put' => ['PUT', '/api/tags/{tag}', ['name' => 'Stolen']],
    'tag patch' => ['PATCH', '/api/tags/{tag}', ['name' => 'Stolen']],
    'tag delete' => ['DELETE', '/api/tags/{tag}'],
]);

it('rejects resources requested through a different parent owned by the same user', function (string $method, string $uri, array $payload = []) {
    $otherProject = Project::factory()->for($this->resourceOwner)->create();
    $otherTask = Task::factory()->for($otherProject)->create();
    Sanctum::actingAs($this->resourceOwner, ['*']);

    $uri = strtr($uri, [
        '{otherProject}' => (string) $otherProject->getKey(),
        '{otherTask}' => (string) $otherTask->getKey(),
        '{task}' => (string) $this->foreignTask->getKey(),
        '{sprint}' => (string) $this->foreignSprint->getKey(),
        '{note}' => (string) $this->foreignNote->getKey(),
        '{checklistItem}' => (string) $this->foreignChecklistItem->getKey(),
    ]);

    $this->json($method, $uri, $payload)->assertNotFound();
})->with([
    'note show parent mismatch' => ['GET', '/api/projects/{otherProject}/notes/{note}'],
    'note put parent mismatch' => ['PUT', '/api/projects/{otherProject}/notes/{note}', ['title' => 'Moved']],
    'note patch parent mismatch' => ['PATCH', '/api/projects/{otherProject}/notes/{note}', ['title' => 'Moved']],
    'note delete parent mismatch' => ['DELETE', '/api/projects/{otherProject}/notes/{note}'],
    'task show parent mismatch' => ['GET', '/api/projects/{otherProject}/tasks/{task}'],
    'task put parent mismatch' => ['PUT', '/api/projects/{otherProject}/tasks/{task}', ['title' => 'Moved']],
    'task patch parent mismatch' => ['PATCH', '/api/projects/{otherProject}/tasks/{task}', ['title' => 'Moved']],
    'task delete parent mismatch' => ['DELETE', '/api/projects/{otherProject}/tasks/{task}'],
    'task status parent mismatch' => ['PATCH', '/api/projects/{otherProject}/tasks/{task}/status', ['status' => 'done']],
    'task sprint parent mismatch' => ['PATCH', '/api/projects/{otherProject}/tasks/{task}/sprint', ['sprint_id' => null]],
    'sprint show parent mismatch' => ['GET', '/api/projects/{otherProject}/sprints/{sprint}'],
    'sprint put parent mismatch' => ['PUT', '/api/projects/{otherProject}/sprints/{sprint}', ['name' => 'Moved']],
    'sprint patch parent mismatch' => ['PATCH', '/api/projects/{otherProject}/sprints/{sprint}', ['name' => 'Moved']],
    'sprint delete parent mismatch' => ['DELETE', '/api/projects/{otherProject}/sprints/{sprint}'],
    'sprint start parent mismatch' => ['POST', '/api/projects/{otherProject}/sprints/{sprint}/start'],
    'sprint complete parent mismatch' => ['POST', '/api/projects/{otherProject}/sprints/{sprint}/complete', ['unfinished_action' => 'backlog']],
    'checklist update parent mismatch' => ['PATCH', '/api/tasks/{otherTask}/checklist/{checklistItem}', ['title' => 'Moved']],
    'checklist delete parent mismatch' => ['DELETE', '/api/tasks/{otherTask}/checklist/{checklistItem}'],
]);

it('does not leak foreign resources through global listings dashboard or search', function () {
    $ownProject = Project::factory()->for($this->attacker)->create(['name' => 'Visible project']);
    $ownTag = Tag::factory()->for($this->attacker)->create(['name' => 'Visible tag']);
    Sanctum::actingAs($this->attacker, ['*']);

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertJsonFragment(['id' => $ownProject->id])
        ->assertJsonMissing(['id' => $this->foreignProject->id])
        ->assertDontSee('ISOLATION-SECRET', false);

    $this->getJson('/api/tags')
        ->assertOk()
        ->assertJsonFragment(['id' => $ownTag->id])
        ->assertJsonMissing(['id' => $this->foreignTag->id])
        ->assertDontSee('ISOLATION-SECRET', false);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertDontSee('ISOLATION-SECRET', false);

    $this->getJson('/api/search?q=ISOLATION-SECRET')
        ->assertOk()
        ->assertJsonCount(0, 'projects')
        ->assertJsonCount(0, 'tasks')
        ->assertJsonCount(0, 'notes')
        ->assertDontSee('ISOLATION-SECRET', false);
});

it('leaves foreign records unchanged after denied write attempts', function () {
    Sanctum::actingAs($this->attacker, ['*']);

    $this->patchJson("/api/projects/{$this->foreignProject->id}", ['name' => 'Stolen'])->assertNotFound();
    $this->patchJson("/api/projects/{$this->foreignProject->id}/tasks/{$this->foreignTask->id}", [
        'title' => 'Stolen',
    ])->assertNotFound();
    $this->patchJson("/api/projects/{$this->foreignProject->id}/sprints/{$this->foreignSprint->id}", [
        'name' => 'Stolen',
    ])->assertNotFound();
    $this->patchJson("/api/projects/{$this->foreignProject->id}/notes/{$this->foreignNote->id}", [
        'title' => 'Stolen',
    ])->assertNotFound();
    $this->patchJson("/api/tags/{$this->foreignTag->id}", ['name' => 'Stolen'])->assertNotFound();
    $this->patchJson("/api/tasks/{$this->foreignTask->id}/checklist/{$this->foreignChecklistItem->id}", [
        'title' => 'Stolen',
    ])->assertNotFound();

    expect($this->foreignProject->fresh()->name)->toBe('ISOLATION-SECRET-PROJECT')
        ->and($this->foreignTask->fresh()->title)->toBe('ISOLATION-SECRET-TASK')
        ->and($this->foreignSprint->fresh()->name)->toBe('ISOLATION-SECRET-SPRINT')
        ->and($this->foreignNote->fresh()->title)->toBe('ISOLATION-SECRET-NOTE')
        ->and($this->foreignTag->fresh()->name)->toBe('ISOLATION-SECRET-TAG')
        ->and($this->foreignChecklistItem->fresh()->title)->toBe('ISOLATION-SECRET-CHECKLIST');
});
