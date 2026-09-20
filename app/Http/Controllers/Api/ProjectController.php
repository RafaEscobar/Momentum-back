<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProjectRequest;
use App\Http\Requests\Api\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $request->user()->projects()->latest()->get();

        return ProjectSummaryResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->projects()->create($request->validated())->refresh();

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        $this->ensureOwnedBy($project, $request);

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->ensureOwnedBy($project, $request);
        $project->update($request->validated());

        return new ProjectResource($project->refresh());
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->ensureOwnedBy($project, $request);
        $project->delete();

        return response()->json(status: 204);
    }

    private function ensureOwnedBy(Project $project, Request $request): void
    {
        abort_unless($project->user_id === $request->user()->getKey(), 404);
    }
}
