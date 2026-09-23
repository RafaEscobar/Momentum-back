<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProjectNoteRequest;
use App\Http\Requests\Api\UpdateProjectNoteRequest;
use App\Http\Resources\ProjectNoteResource;
use App\Http\Resources\ProjectNoteSummaryResource;
use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectNoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [ProjectNote::class, $project]);

        $notes = $project->notes()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return ProjectNoteSummaryResource::collection($notes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectNoteRequest $request, Project $project): JsonResponse
    {
        $note = $project->notes()->create($request->validated())->refresh();

        return (new ProjectNoteResource($note))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, ProjectNote $note): ProjectNoteResource
    {
        Gate::authorize('view', $note);

        return new ProjectNoteResource($note);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateProjectNoteRequest $request,
        Project $project,
        ProjectNote $note,
    ): ProjectNoteResource {
        $note->update($request->validated());

        return new ProjectNoteResource($note->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, ProjectNote $note): JsonResponse
    {
        Gate::authorize('delete', $note);
        $note->delete();

        return response()->json(status: 204);
    }
}
