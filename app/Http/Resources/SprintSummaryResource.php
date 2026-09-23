<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SprintSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status->value,
            'planned_points' => $this->resource->plannedPoints(),
            'completed_points' => $this->resource->completedPoints(),
            'progress_percentage' => $this->resource->progressPercentage(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
