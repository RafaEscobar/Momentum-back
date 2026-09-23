<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChecklistItemRequest;
use App\Http\Requests\Api\UpdateChecklistItemRequest;
use App\Http\Resources\ChecklistItemResource;
use App\Models\ChecklistItem;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ChecklistItemController extends Controller
{
    public function store(StoreChecklistItemRequest $request, Task $task): JsonResponse
    {
        $item = $task->checklistItems()->create($request->validated())->refresh();

        return (new ChecklistItemResource($item))->response()->setStatusCode(201);
    }

    public function update(
        UpdateChecklistItemRequest $request,
        Task $task,
        ChecklistItem $checklistItem,
    ): ChecklistItemResource {
        $checklistItem->update($request->validated());

        return new ChecklistItemResource($checklistItem->refresh());
    }

    public function destroy(Task $task, ChecklistItem $checklistItem): JsonResponse
    {
        Gate::authorize('delete', $checklistItem);
        $checklistItem->delete();

        return response()->json(status: 204);
    }
}
