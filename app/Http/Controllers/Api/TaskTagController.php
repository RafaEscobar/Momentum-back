<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncTaskTagsRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class TaskTagController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SyncTaskTagsRequest $request, Task $task): TaskResource
    {
        DB::transaction(function () use ($request, $task): void {
            $task->tags()->sync($request->validated('tag_ids'));
        });

        return new TaskResource($task->load('tags'));
    }
}
