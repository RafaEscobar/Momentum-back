<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;

class TaskStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateTaskStatusRequest $request, Project $project, Task $task): TaskResource
    {
        $status = TaskStatus::from($request->validated('status'));

        $task->update([
            'status' => $status,
            'completed_at' => $status === TaskStatus::Done ? ($task->completed_at ?? now()) : null,
        ]);

        return new TaskResource($task->refresh());
    }
}
