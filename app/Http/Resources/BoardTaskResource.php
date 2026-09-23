<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoardTaskResource extends JsonResource
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
            'sprint_id' => $this->sprint_id,
            'title' => $this->title,
            'type' => $this->type->value,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'story_points' => $this->story_points,
            'position' => $this->position,
            'checklist' => [
                'total' => $this->checklist_items_count,
                'completed' => $this->completed_checklist_items_count,
            ],
            'tags' => $this->relationLoaded('tags')
                ? $this->tags->map(fn ($tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->values()
                : [],
        ];
    }
}
