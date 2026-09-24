<?php

use App\Enums\ActivityType;
use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Enums\UnfinishedTaskAction;
use App\Models\Activity;
use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Sprint;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Database\QueryException;
use Laravel\Sanctum\Sanctum;

it('enforces one active sprint per project at the database boundary', function () {
    $project = Project::factory()->create();
    $firstSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    $secondSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);

    $firstSprint->update(['status' => SprintStatus::Active]);

    expect(fn () => $secondSprint->update(['status' => SprintStatus::Active]))
        ->toThrow(QueryException::class);

    expect($firstSprint->refresh()->status)->toBe(SprintStatus::Active)
        ->and($secondSprint->refresh()->status)->toBe(SprintStatus::Planned)
        ->and(Sprint::query()
            ->where('project_id', $project->id)
            ->where('status', SprintStatus::Active)
            ->count())->toBe(1);
});

it('serializes competing sprint starts and leaves one winner', function () {
    $project = Project::factory()->create();
    $firstSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    $secondSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);
    Sanctum::actingAs($project->user, ['*']);

    $this->postJson("/api/projects/{$project->id}/sprints/{$firstSprint->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', SprintStatus::Active->value);
    $this->postJson("/api/projects/{$project->id}/sprints/{$secondSprint->id}/start")
        ->assertUnprocessable()
        ->assertInvalid(['status']);

    expect($firstSprint->refresh()->status)->toBe(SprintStatus::Active)
        ->and($secondSprint->refresh()->status)->toBe(SprintStatus::Planned)
        ->and(Sprint::query()
            ->where('project_id', $project->id)
            ->where('status', SprintStatus::Active)
            ->count())->toBe(1);
});

it('rejects a repeated sprint completion without repeating side effects', function () {
    $sprint = Sprint::factory()->create(['status' => SprintStatus::Active]);
    $task = Task::factory()->for($sprint->project)->create([
        'sprint_id' => $sprint->id,
        'status' => TaskStatus::Todo,
    ]);
    Sanctum::actingAs($sprint->project->user, ['*']);
    $url = "/api/projects/{$sprint->project_id}/sprints/{$sprint->id}/complete";
    $payload = ['unfinished_action' => UnfinishedTaskAction::Backlog->value];

    $this->postJson($url, $payload)->assertOk();
    $completedAt = $sprint->refresh()->completed_at?->toISOString();
    $this->postJson($url, $payload)->assertUnprocessable()->assertInvalid(['status']);

    expect($sprint->refresh()->completed_at?->toISOString())->toBe($completedAt)
        ->and($task->refresh()->sprint_id)->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Backlog)
        ->and(Activity::query()
            ->where('subject_type', $sprint->getMorphClass())
            ->where('subject_id', $sprint->id)
            ->where('type', ActivityType::SprintCompleted)
            ->count())->toBe(1);
});

it('applies competing task and checklist orders atomically with last commit winning', function () {
    $project = Project::factory()->create();
    $firstTask = Task::factory()->for($project)->create(['position' => 1]);
    $secondTask = Task::factory()->for($project)->create(['position' => 2]);
    $firstItem = ChecklistItem::factory()->for($firstTask)->create(['position' => 1]);
    $secondItem = ChecklistItem::factory()->for($firstTask)->create(['position' => 2]);
    Sanctum::actingAs($project->user, ['*']);
    $taskUrl = "/api/projects/{$project->id}/tasks/reorder";
    $checklistUrl = "/api/tasks/{$firstTask->id}/checklist/reorder";

    $this->patchJson($taskUrl, ['tasks' => [
        ['id' => $secondTask->id, 'status' => TaskStatus::InProgress->value, 'position' => 1],
        ['id' => $firstTask->id, 'status' => TaskStatus::Todo->value, 'position' => 2],
    ]])->assertOk();
    $this->patchJson($taskUrl, ['tasks' => [
        ['id' => $firstTask->id, 'status' => TaskStatus::Done->value, 'position' => 1],
        ['id' => $secondTask->id, 'status' => TaskStatus::Todo->value, 'position' => 2],
    ]])->assertOk();

    $this->patchJson($checklistUrl, ['items' => [
        ['id' => $secondItem->id, 'position' => 1],
        ['id' => $firstItem->id, 'position' => 2],
    ]])->assertOk();
    $this->patchJson($checklistUrl, ['items' => [
        ['id' => $firstItem->id, 'position' => 1],
        ['id' => $secondItem->id, 'position' => 2],
    ]])->assertOk();

    expect($firstTask->refresh()->position)->toBe(1)
        ->and($firstTask->status)->toBe(TaskStatus::Done)
        ->and($firstTask->completed_at)->not->toBeNull()
        ->and($secondTask->refresh()->position)->toBe(2)
        ->and($secondTask->status)->toBe(TaskStatus::Todo)
        ->and($secondTask->completed_at)->toBeNull()
        ->and($firstItem->refresh()->position)->toBe(1)
        ->and($secondItem->refresh()->position)->toBe(2);
});

it('makes identical reorder and tag sync retries idempotent', function () {
    $task = Task::factory()->create(['position' => 1]);
    $item = ChecklistItem::factory()->for($task)->create(['position' => 1]);
    $tag = Tag::factory()->for($task->project->user)->create();
    Sanctum::actingAs($task->project->user, ['*']);
    $taskPayload = ['tasks' => [[
        'id' => $task->id,
        'status' => TaskStatus::Todo->value,
        'position' => 3,
    ]]];
    $itemPayload = ['items' => [['id' => $item->id, 'position' => 3]]];

    foreach (range(1, 2) as $attempt) {
        $this->patchJson("/api/projects/{$task->project_id}/tasks/reorder", $taskPayload)->assertOk();
        $this->patchJson("/api/tasks/{$task->id}/checklist/reorder", $itemPayload)->assertOk();
        $this->putJson("/api/tasks/{$task->id}/tags", ['tag_ids' => [$tag->id]])->assertOk();
    }

    expect($task->refresh()->position)->toBe(3)
        ->and($item->refresh()->position)->toBe(3)
        ->and($task->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
    $this->assertDatabaseCount('tag_task', 1);
});

it('enforces cascades and nulling when parent resources are deleted', function () {
    $project = Project::factory()->create();
    $sprint = Sprint::factory()->for($project)->create();
    $task = Task::factory()->for($project)->create(['sprint_id' => $sprint->id]);
    $item = ChecklistItem::factory()->for($task)->create();
    $note = ProjectNote::factory()->for($project)->create();
    $tag = Tag::factory()->for($project->user)->create();
    $task->tags()->attach($tag);
    Activity::factory()->create([
        'user_id' => $project->user_id,
        'project_id' => $project->id,
    ]);

    $sprint->delete();
    expect($task->refresh()->sprint_id)->toBeNull();

    $project->delete();

    $this->assertModelMissing($project);
    $this->assertModelMissing($task);
    $this->assertModelMissing($item);
    $this->assertModelMissing($note);
    $this->assertModelExists($tag);
    $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id, 'tag_id' => $tag->id]);
    $this->assertDatabaseMissing('activities', ['project_id' => $project->id]);
});

it('returns final state responses when delete requests race or retry', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    $note = ProjectNote::factory()->for($project)->create();
    $tag = Tag::factory()->for($project->user)->create();
    Sanctum::actingAs($project->user, ['*']);

    $operations = [
        ["/api/projects/{$project->id}/notes/{$note->id}", 'deleteJson'],
        ["/api/projects/{$project->id}/tasks/{$task->id}", 'deleteJson'],
        ["/api/tags/{$tag->id}", 'deleteJson'],
    ];

    foreach ($operations as [$url, $method]) {
        $this->{$method}($url)->assertNoContent();
        $this->{$method}($url)->assertNotFound();
    }

    $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();
    $this->deleteJson("/api/projects/{$project->id}")->assertNotFound();
});
