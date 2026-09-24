<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;

class TaskStatusController extends Controller
{
    public function __construct(private readonly ActivityService $activityService) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateTaskStatusRequest $request, Project $project, Task $task): TaskResource
    {
        $status = TaskStatus::from($request->validated('status'));

        $task = DB::transaction(function () use ($status, $task): Task {
            $previousStatus = $task->status;

            $task->update([
                'status' => $status,
                'completed_at' => $status === TaskStatus::Done ? ($task->completed_at ?? now()) : null,
            ]);

            $task->refresh();
            $this->activityService->taskStatusChanged($task, $previousStatus);

            return $task;
        });

        return new TaskResource($task);
    }
}
