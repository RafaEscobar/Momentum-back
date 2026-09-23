<?php

namespace App\Http\Controllers\Api;

use App\Enums\SprintStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SprintSummaryResource;
use App\Models\Project;
use App\Services\TaskStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectStatsController extends Controller
{
    public function __construct(private readonly TaskStatisticsService $taskStatistics) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $statistics = $this->taskStatistics->forProject($project);
        $activeSprint = $project->sprints()
            ->where('status', SprintStatus::Active)
            ->withPointTotals()
            ->first();

        return response()->json([
            'progress' => $statistics['progress'],
            'story_points' => [
                'total' => $statistics['total_story_points'],
                'completed' => $statistics['completed_story_points'],
            ],
            'tasks' => [
                'total' => $statistics['total'],
                'backlog' => $statistics['backlog'],
                'todo' => $statistics['todo'],
                'in_progress' => $statistics['in_progress'],
                'blocked' => $statistics['blocked'],
                'done' => $statistics['done'],
            ],
            'active_sprint' => $activeSprint === null
                ? null
                : (new SprintSummaryResource($activeSprint))->resolve($request),
        ]);
    }
}
