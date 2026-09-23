<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReorderTasksRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskReorderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReorderTasksRequest $request, Project $project): AnonymousResourceCollection
    {
        $taskUpdates = $request->validated('tasks');

        /** @var Collection<int, Task> $tasks */
        $tasks = DB::transaction(function () use ($project, $taskUpdates): Collection {
            $taskIds = collect($taskUpdates)->pluck('id');
            $tasksById = $project->tasks()
                ->whereKey($taskIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Task $task): int => $task->getKey());

            if ($tasksById->count() !== $taskIds->count()) {
                throw ValidationException::withMessages([
                    'tasks' => ['Every task must belong to the selected project.'],
                ]);
            }

            return collect($taskUpdates)->map(function (array $taskUpdate) use ($tasksById): Task {
                $task = $tasksById->get($taskUpdate['id']);
                $status = TaskStatus::from($taskUpdate['status']);

                $task->update([
                    'status' => $status,
                    'position' => $taskUpdate['position'],
                    'completed_at' => $status === TaskStatus::Done ? ($task->completed_at ?? now()) : null,
                ]);

                return $task->refresh();
            });
        });

        return TaskResource::collection($tasks);
    }
}
