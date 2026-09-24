<?php

use App\Http\Resources\ProjectResource;
use App\Models\Project;

it('includes the task count only when it has been loaded', function () {
    $project = Project::factory()->create();

    $withoutCount = (new ProjectResource($project))->resolve(request());

    expect($withoutCount)->not->toHaveKey('tasks_count');

    $project->setAttribute('tasks_count', 3);
    $withCount = (new ProjectResource($project))->resolve(request());

    expect($withCount)->toHaveKey('tasks_count', 3);
});
