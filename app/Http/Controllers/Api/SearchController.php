<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Http\Resources\ProjectNoteSummaryResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Http\Resources\TaskSummaryResource;
use App\Models\ProjectNote;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchController extends Controller
{
    private const RESULTS_PER_TYPE = 10;

    /**
     * Handle the incoming request.
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $user = $request->user();
        $term = addcslashes($request->validated('q'), '\\%_');
        $pattern = "%{$term}%";

        $projects = $user->projects()
            ->select(['id', 'user_id', 'name', 'status', 'priority', 'color', 'icon', 'created_at', 'updated_at'])
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhere('description', 'like', $pattern))
            ->withTaskPointTotals()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::RESULTS_PER_TYPE, pageName: 'projects_page');

        $tasks = Task::query()
            ->select([
                'id', 'project_id', 'sprint_id', 'title', 'type', 'priority', 'status',
                'story_points', 'position', 'completed_at', 'created_at', 'updated_at',
            ])
            ->whereHas('project', fn (Builder $query) => $query->where('user_id', $user->getKey()))
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', $pattern)
                ->orWhere('description', 'like', $pattern))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::RESULTS_PER_TYPE, pageName: 'tasks_page');

        $notes = ProjectNote::query()
            ->select(['id', 'project_id', 'title', 'created_at', 'updated_at'])
            ->whereHas('project', fn (Builder $query) => $query->where('user_id', $user->getKey()))
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', $pattern)
                ->orWhere('content', 'like', $pattern))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::RESULTS_PER_TYPE, pageName: 'notes_page');

        return response()->json([
            'projects' => ProjectSummaryResource::collection($projects->getCollection())->resolve($request),
            'tasks' => TaskSummaryResource::collection($tasks->getCollection())->resolve($request),
            'notes' => ProjectNoteSummaryResource::collection($notes->getCollection())->resolve($request),
            'meta' => [
                'projects' => $this->paginationMeta($projects),
                'tasks' => $this->paginationMeta($tasks),
                'notes' => $this->paginationMeta($notes),
            ],
        ]);
    }

    /** @return array{current_page: int, last_page: int, per_page: int, total: int} */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
