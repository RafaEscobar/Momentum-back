<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexGeneralNoteRequest;
use App\Http\Requests\Api\StoreGeneralNoteRequest;
use App\Http\Requests\Api\UpdateGeneralNoteRequest;
use App\Http\Resources\GeneralNoteResource;
use App\Http\Resources\GeneralNoteSummaryResource;
use App\Models\GeneralNote;
use App\Support\SqlLikePattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GeneralNoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexGeneralNoteRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $notes = $request->user()->generalNotes()
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(
                'title',
                'like',
                SqlLikePattern::contains($search),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return GeneralNoteSummaryResource::collection($notes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGeneralNoteRequest $request): JsonResponse
    {
        $note = $request->user()->generalNotes()->create($request->validated())->refresh();

        return (new GeneralNoteResource($note))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(GeneralNote $note): GeneralNoteResource
    {
        Gate::authorize('view', $note);

        return new GeneralNoteResource($note);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGeneralNoteRequest $request, GeneralNote $note): GeneralNoteResource
    {
        $note->update($request->validated());

        return new GeneralNoteResource($note->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GeneralNote $note): JsonResponse
    {
        Gate::authorize('delete', $note);
        $note->delete();

        return response()->json(status: 204);
    }
}
