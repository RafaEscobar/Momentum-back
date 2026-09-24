<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReorderChecklistItemsRequest;
use App\Http\Resources\ChecklistItemResource;
use App\Models\ChecklistItem;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChecklistReorderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReorderChecklistItemsRequest $request, Task $task): AnonymousResourceCollection
    {
        $itemUpdates = $request->validated('items');

        /** @var Collection<int, ChecklistItem> $items */
        $items = DB::transaction(function () use ($task, $itemUpdates): Collection {
            $itemIds = collect($itemUpdates)->pluck('id');
            $itemsById = $task->checklistItems()
                ->whereKey($itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (ChecklistItem $item): int => $item->getKey());

            if ($itemsById->count() !== $itemIds->count()) {
                throw ValidationException::withMessages([
                    'items' => ['Every checklist item must belong to the selected task.'],
                ]);
            }

            return collect($itemUpdates)->map(function (array $itemUpdate) use ($itemsById): ChecklistItem {
                $item = $itemsById->get($itemUpdate['id']);
                $item->update(['position' => $itemUpdate['position']]);

                return $item->refresh();
            });
        }, attempts: 3);

        return ChecklistItemResource::collection($items);
    }
}
