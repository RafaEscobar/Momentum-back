<?php

use App\Models\ChecklistItem;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a checklist item for an owned task', function () {
    $task = Task::factory()->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->postJson("/api/tasks/{$task->id}/checklist", [
        'title' => 'Add validation',
        'position' => 2,
    ])->assertCreated()
        ->assertJsonPath('data.task_id', $task->id)
        ->assertJsonPath('data.title', 'Add validation')
        ->assertJsonPath('data.is_completed', false);
});

it('validates checklist item data', function () {
    $task = Task::factory()->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->postJson("/api/tasks/{$task->id}/checklist", [
        'title' => str_repeat('a', 256),
        'position' => -1,
    ])->assertUnprocessable()->assertInvalid(['title', 'position']);
});

it('updates title and completion state', function () {
    $item = ChecklistItem::factory()->create();
    Sanctum::actingAs($item->task->project->user, ['*']);

    $this->patchJson("/api/tasks/{$item->task_id}/checklist/{$item->id}", [
        'title' => 'Updated item',
        'is_completed' => true,
    ])->assertOk()
        ->assertJsonPath('data.title', 'Updated item')
        ->assertJsonPath('data.is_completed', true);

    $this->patchJson("/api/tasks/{$item->task_id}/checklist/{$item->id}", [
        'is_completed' => false,
    ])->assertOk()
        ->assertJsonPath('data.is_completed', false);
});

it('deletes a checklist item', function () {
    $item = ChecklistItem::factory()->create();
    Sanctum::actingAs($item->task->project->user, ['*']);

    $this->deleteJson("/api/tasks/{$item->task_id}/checklist/{$item->id}")->assertNoContent();

    $this->assertModelMissing($item);
});

it('returns 404 for an item requested through a different task', function () {
    $item = ChecklistItem::factory()->create();
    $otherTask = Task::factory()->for($item->task->project)->create();
    Sanctum::actingAs($item->task->project->user, ['*']);

    $this->patchJson("/api/tasks/{$otherTask->id}/checklist/{$item->id}", ['is_completed' => true])
        ->assertNotFound();
    $this->deleteJson("/api/tasks/{$otherTask->id}/checklist/{$item->id}")->assertNotFound();
});

it('hides checklist operations from another user', function () {
    $item = ChecklistItem::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson("/api/tasks/{$item->task_id}/checklist", ['title' => 'Forbidden'])->assertNotFound();
    $this->patchJson("/api/tasks/{$item->task_id}/checklist/{$item->id}", ['is_completed' => true])->assertNotFound();
    $this->deleteJson("/api/tasks/{$item->task_id}/checklist/{$item->id}")->assertNotFound();
});
