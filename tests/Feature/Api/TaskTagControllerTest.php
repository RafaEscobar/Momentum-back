<?php

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('syncs tags owned by the task owner and returns the updated task', function () {
    $task = Task::factory()->create();
    $firstTag = Tag::factory()->for($task->project->user)->create();
    $secondTag = Tag::factory()->for($task->project->user)->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->putJson("/api/tasks/{$task->id}/tags", [
        'tag_ids' => [$firstTag->id, $secondTag->id],
    ])->assertOk()
        ->assertJsonCount(2, 'data.tags')
        ->assertJsonPath('data.tags.0.id', $firstTag->id)
        ->assertJsonPath('data.tags.1.id', $secondTag->id);

    $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $firstTag->id]);
    $this->assertDatabaseHas('tag_task', ['task_id' => $task->id, 'tag_id' => $secondTag->id]);
});

it('replaces existing tags and supports removing every tag', function () {
    $task = Task::factory()->create();
    $oldTag = Tag::factory()->for($task->project->user)->create();
    $newTag = Tag::factory()->for($task->project->user)->create();
    $task->tags()->attach($oldTag);
    Sanctum::actingAs($task->project->user, ['*']);

    $this->putJson("/api/tasks/{$task->id}/tags", ['tag_ids' => [$newTag->id]])
        ->assertOk()
        ->assertJsonCount(1, 'data.tags')
        ->assertJsonPath('data.tags.0.id', $newTag->id);
    $this->assertDatabaseMissing('tag_task', ['task_id' => $task->id, 'tag_id' => $oldTag->id]);

    $this->putJson("/api/tasks/{$task->id}/tags", ['tag_ids' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data.tags');
});

it('rejects tags owned by another user before syncing', function () {
    $task = Task::factory()->create();
    $ownedTag = Tag::factory()->for($task->project->user)->create();
    $foreignTag = Tag::factory()->create();
    $task->tags()->attach($ownedTag);
    Sanctum::actingAs($task->project->user, ['*']);

    $this->putJson("/api/tasks/{$task->id}/tags", [
        'tag_ids' => [$foreignTag->id],
    ])->assertUnprocessable()->assertInvalid(['tag_ids.0']);

    expect($task->tags()->pluck('tags.id')->all())->toBe([$ownedTag->id]);
});

it('validates duplicate tag ids and requires the tag array', function () {
    $task = Task::factory()->create();
    $tag = Tag::factory()->for($task->project->user)->create();
    Sanctum::actingAs($task->project->user, ['*']);

    $this->putJson("/api/tasks/{$task->id}/tags", ['tag_ids' => [$tag->id, $tag->id]])
        ->assertUnprocessable()
        ->assertInvalid(['tag_ids.0', 'tag_ids.1']);
    $this->putJson("/api/tasks/{$task->id}/tags", [])
        ->assertUnprocessable()
        ->assertInvalid(['tag_ids']);
});

it('hides tag assignment for another users task', function () {
    $task = Task::factory()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->putJson("/api/tasks/{$task->id}/tags", ['tag_ids' => []])->assertNotFound();
});
