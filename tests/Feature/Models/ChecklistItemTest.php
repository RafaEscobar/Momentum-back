<?php

use App\Models\ChecklistItem;
use App\Models\Task;

it('creates checklist items with defaults and casts', function () {
    $task = Task::factory()->create();

    $item = $task->checklistItems()->create(['title' => 'Write tests'])->refresh();

    expect($item->is_completed)->toBeFalse()
        ->and($item->position)->toBe(0)
        ->and($item->task->is($task))->toBeTrue();
});

it('exposes checklist items through the task relationship', function () {
    $item = ChecklistItem::factory()->create();

    expect($item->task->checklistItems()->whereKey($item)->exists())->toBeTrue();
});

it('deletes checklist items with their task', function () {
    $item = ChecklistItem::factory()->create();

    $item->task->delete();

    $this->assertModelMissing($item);
});
