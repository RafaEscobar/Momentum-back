<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexBacklogRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Schema;

class BacklogController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(IndexBacklogRequest $request, Project $project): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $project->tasks()
            ->whereNull('sprint_id')
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"));

        if ($filters['tag_ids'] ?? null) {
            if (Schema::hasTable('tag_task')) {
                $query->whereExists(function ($tagQuery) use ($filters): void {
                    $tagQuery->selectRaw('1')
                        ->from('tag_task')
                        ->whereColumn('tag_task.task_id', 'tasks.id')
                        ->whereIn('tag_task.tag_id', $filters['tag_ids']);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $storyPointsTotal = (int) (clone $query)->sum('story_points');
        $tasks = $query
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return TaskResource::collection($tasks)->additional([
            'meta' => ['story_points_total' => $storyPointsTotal],
        ]);
    }
}
