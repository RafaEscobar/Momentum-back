<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Http\Resources\SprintSummaryResource;
use App\Models\Sprint;
use App\Services\TaskStatisticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly TaskStatisticsService $taskStatistics) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $projects = $user->projects()
            ->where('status', ProjectStatus::Active)
            ->withTaskPointTotals()
            ->withCount('tasks')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $activeSprints = Sprint::query()
            ->where('status', SprintStatus::Active)
            ->whereHas('project', fn (Builder $query) => $query->where('user_id', $user->getKey()))
            ->withPointTotals()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $statistics = $this->taskStatistics->forUser($user);
        $recentActivity = $user->activities()
            ->with('subject')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json([
            'projects' => ProjectSummaryResource::collection($projects)->resolve($request),
            'active_sprints' => SprintSummaryResource::collection($activeSprints)->resolve($request),
            'summary' => [
                'active_projects' => $projects->count(),
                'planned_story_points' => $statistics['total_story_points'],
                'completed_story_points' => $statistics['completed_story_points'],
                'pending_tasks' => $statistics['pending'],
                'in_progress_tasks' => $statistics['in_progress'],
                'completed_tasks' => $statistics['done'],
            ],
            'recent_activity' => ActivityResource::collection($recentActivity)->resolve($request),
        ]);
    }
}
