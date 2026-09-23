<?php

use App\Models\ChecklistItem;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;

it('reorders checklist items in payload order', function () {
    $task = Task::factory()->create();
    $firstItem = ChecklistItem::factory()->for($task)->create(['position' => 1]);
    $secondItem = ChecklistItem::factory()->for($task)->create(['position' => 2]);
    Sanctum::actingAs($task->project->user, ['*']);

    $this->patchJson("/api/tasks/{$task->id}/checklist/reorder", [
        'items' => [
            ['id' => $secondItem->id, 'position' => 1],
            ['id' => $firstItem->id, 'position' => 2],
        ],
    ])->assertOk()
        ->assertJsonPath('data.0.id', $secondItem->id)
        ->assertJsonPath('data.0.position', 1)
        ->assertJsonPath('data.1.id', $firstItem->id)
        ->assertJsonPath('data.1.position', 2);
});

it('validates all checklist ids before the first write', function () {
    $task = Task::factory()->create();
    $item = ChecklistItem::factory()->for($task)->create(['position' => 1]);
    $foreignItem = ChecklistItem::factory()->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->patchJson("/api/tasks/{$task->id}/checklist/reorder", [
        'items' => [
            ['id' => $item->id, 'position' => 9],
            ['id' => $foreignItem->id, 'position' => 10],
        ],
    ])->assertUnprocessable()->assertInvalid(['items']);

    expect($item->refresh()->position)->toBe(1);
});

it('rejects duplicate checklist ids', function () {
    $item = ChecklistItem::factory()->create();
    Sanctum::actingAs($item->task->project->user, ['*']);

    $this->patchJson("/api/tasks/{$item->task_id}/checklist/reorder", [
        'items' => [
            ['id' => $item->id, 'position' => 1],
            ['id' => $item->id, 'position' => 2],
        ],
    ])->assertUnprocessable()->assertInvalid(['items.0.id', 'items.1.id']);
});
