<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTaskSprintRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;

class TaskSprintController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateTaskSprintRequest $request, Project $project, Task $task): TaskResource
    {
        $sprintId = $request->validated('sprint_id');
        $status = $sprintId === null
            ? TaskStatus::Backlog
            : ($task->status === TaskStatus::Backlog ? TaskStatus::Todo : $task->status);

        $task->update([
            'sprint_id' => $sprintId,
            'status' => $status,
        ]);

        return new TaskResource($task->refresh());
    }
}
