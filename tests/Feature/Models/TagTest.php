<?php

use App\Models\Tag;
use App\Models\Task;

it('belongs to a user', function () {
    $tag = Tag::factory()->create();

    expect($tag->user->tags()->whereKey($tag)->exists())->toBeTrue();
});

it('has a many to many relationship with tasks', function () {
    $tag = Tag::factory()->create();
    $task = Task::factory()->create();

    $tag->tasks()->attach($task);

    expect($tag->tasks->first()->is($task))->toBeTrue()
        ->and($task->tags->first()->is($tag))->toBeTrue();
});

it('removes tag assignments when a tag is deleted', function () {
    $tag = Tag::factory()->create();
    $task = Task::factory()->create();
    $tag->tasks()->attach($task);

    $tag->delete();

    $this->assertDatabaseMissing('tag_task', ['tag_id' => $tag->id, 'task_id' => $task->id]);
});
