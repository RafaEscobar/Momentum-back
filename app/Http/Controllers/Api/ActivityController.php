<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(IndexActivityRequest $request, Project $project): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $activities = $project->activities()
            ->with('subject')
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->where(
                'created_at',
                '>=',
                CarbonImmutable::parse($date)->startOfDay(),
            ))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->where(
                'created_at',
                '<',
                CarbonImmutable::parse($date)->addDay()->startOfDay(),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return ActivityResource::collection($activities);
    }
}
