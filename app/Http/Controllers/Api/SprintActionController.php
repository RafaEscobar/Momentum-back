<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SprintResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\SprintService;
use Illuminate\Support\Facades\Gate;

class SprintActionController extends Controller
{
    public function __construct(private readonly SprintService $sprintService) {}

    public function start(Project $project, Sprint $sprint): SprintResource
    {
        Gate::authorize('update', $sprint);

        return new SprintResource($this->sprintService->start($sprint)->freshWithPointTotals());
    }

    public function complete(Project $project, Sprint $sprint): SprintResource
    {
        Gate::authorize('update', $sprint);

        return new SprintResource($this->sprintService->complete($sprint)->freshWithPointTotals());
    }
}
