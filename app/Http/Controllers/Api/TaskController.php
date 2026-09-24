<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexTaskRequest;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Requests\Api\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskSummaryResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function __construct(private readonly ActivityService $activityService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexTaskRequest $request, Project $project): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $tasks = $project->tasks()
            ->filter($filters)
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return TaskSummaryResource::collection($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $task = DB::transaction(function () use ($request, $project): Task {
            $task = $project->tasks()->create($request->validated())->refresh();
            $this->activityService->taskCreated($task);

            return $task;
        });

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task): TaskResource
    {
        $task = DB::transaction(function () use ($request, $task): Task {
            $previousStatus = $task->status;
            $data = $request->validated();

            if (array_key_exists('status', $data)) {
                $status = TaskStatus::from($data['status']);
                $data['completed_at'] = $status === TaskStatus::Done
                    ? ($task->completed_at ?? now())
                    : null;
            }

            $task->update($data);
            $task->refresh();
            $this->activityService->taskStatusChanged($task, $previousStatus);

            return $task;
        });

        return new TaskResource($task);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);
        $task->delete();

        return response()->json(status: 204);
    }
}
