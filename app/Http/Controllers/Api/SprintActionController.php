<?php

namespace App\Http\Controllers\Api;

use App\Enums\UnfinishedTaskAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteSprintRequest;
use App\Http\Resources\SprintResource;
use App\Http\Resources\TaskSummaryResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Services\SprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SprintActionController extends Controller
{
    public function __construct(private readonly SprintService $sprintService) {}

    public function start(Project $project, Sprint $sprint): SprintResource
    {
        Gate::authorize('update', $sprint);

        return new SprintResource($this->sprintService->start($sprint)->freshWithPointTotals());
    }

    public function complete(
        CompleteSprintRequest $request,
        Project $project,
        Sprint $sprint,
    ): JsonResponse {
        $action = UnfinishedTaskAction::from($request->validated('unfinished_action'));
        $result = $this->sprintService->complete(
            sprint: $sprint,
            unfinishedAction: $action,
            nextSprintId: $request->validated('next_sprint_id'),
        );
        $movedTasks = Task::query()
            ->whereKey($result->movedTaskIds)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $response = [
            'sprint' => (new SprintResource($result->sprint->freshWithPointTotals()))->resolve($request),
            'summary' => $result->summary,
            'moved_tasks' => TaskSummaryResource::collection($movedTasks)->resolve($request),
        ];

        if ($request->boolean('include_completed_tasks')) {
            $completedTasks = Task::query()
                ->whereKey($result->completedTaskIds)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $response['completed_tasks'] = TaskSummaryResource::collection($completedTasks)->resolve($request);
        }

        return response()->json($response);
    }
}
