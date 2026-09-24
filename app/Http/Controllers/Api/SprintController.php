<?php

namespace App\Http\Controllers\Api;

use App\Enums\SprintStatus;
use App\Enums\UnfinishedTaskAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexSprintRequest;
use App\Http\Requests\Api\StoreSprintRequest;
use App\Http\Requests\Api\UpdateSprintRequest;
use App\Http\Resources\SprintResource;
use App\Http\Resources\SprintSummaryResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\SprintService;
use App\Support\SqlLikePattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SprintController extends Controller
{
    public function __construct(private readonly SprintService $sprintService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexSprintRequest $request, Project $project): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $sprints = $project->sprints()
            ->withPointTotals()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(
                'name',
                'like',
                SqlLikePattern::contains($search),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return SprintSummaryResource::collection($sprints);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSprintRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $status = SprintStatus::tryFrom($data['status'] ?? '') ?? SprintStatus::Planned;

        $sprint = DB::transaction(function () use ($project, $data, $status): Sprint {
            $attributes = $status === SprintStatus::Active ? Arr::except($data, 'status') : $data;
            $sprint = $project->sprints()->create($attributes);

            return $status === SprintStatus::Active
                ? $this->sprintService->activate($sprint)
                : $sprint->refresh();
        });

        return (new SprintResource($sprint->freshWithPointTotals()))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, Sprint $sprint): SprintResource
    {
        Gate::authorize('view', $sprint);

        return new SprintResource($sprint->freshWithPointTotals());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSprintRequest $request, Project $project, Sprint $sprint): SprintResource
    {
        $data = $request->validated();

        $sprint = DB::transaction(function () use ($sprint, $data): Sprint {
            $sprint->update(Arr::except($data, 'status'));

            if (! array_key_exists('status', $data)) {
                return $sprint->refresh();
            }

            $status = SprintStatus::from($data['status']);

            if ($status === SprintStatus::Active) {
                return $this->sprintService->activate($sprint);
            }

            if ($status === SprintStatus::Completed) {
                return $this->sprintService->complete($sprint, UnfinishedTaskAction::Backlog)->sprint;
            }

            $sprint->update(['status' => $status]);

            return $sprint->refresh();
        });

        return new SprintResource($sprint->freshWithPointTotals());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Sprint $sprint): JsonResponse
    {
        Gate::authorize('delete', $sprint);
        $sprint->delete();

        return response()->json(status: 204);
    }
}
