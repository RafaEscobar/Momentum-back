<?php

namespace App\Http\Controllers\Api;

use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShowBoardRequest;
use App\Http\Resources\BoardTaskResource;
use App\Http\Resources\SprintSummaryResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class BoardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ShowBoardRequest $request, Project $project): JsonResponse
    {
        $sprint = $this->selectedSprint($request, $project);
        $query = $project->tasks()
            ->where(function (Builder $query) use ($sprint): void {
                $query->whereNull('sprint_id');

                if ($sprint) {
                    $query->orWhere('sprint_id', $sprint->getKey());
                }
            })
            ->withCount([
                'checklistItems',
                'checklistItems as completed_checklist_items_count' => fn (Builder $query) => $query->where('is_completed', true),
            ])
            ->orderBy('position')
            ->orderBy('id');

        $tasks = $query->with('tags')->get();
        $columns = collect(TaskStatus::cases())->mapWithKeys(
            fn (TaskStatus $status): array => [
                $status->value => $tasks
                    ->where('status', $status)
                    ->map(fn (Task $task): array => (new BoardTaskResource($task))->resolve($request))
                    ->values(),
            ]
        );

        return response()->json([
            'sprint' => $sprint ? (new SprintSummaryResource($sprint))->resolve($request) : null,
            ...$columns->all(),
        ]);
    }

    private function selectedSprint(ShowBoardRequest $request, Project $project): ?Sprint
    {
        $sprintId = $request->validated('sprint_id');

        return $sprintId
            ? $project->sprints()->withPointTotals()->findOrFail($sprintId)
            : $project->sprints()->withPointTotals()->where('status', SprintStatus::Active)->first();
    }
}
