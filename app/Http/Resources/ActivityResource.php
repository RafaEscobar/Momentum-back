<?php

namespace App\Http\Resources;

use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'type' => $this->type->value,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'subject' => $this->whenLoaded('subject', fn (): ?array => $this->subjectSummary($this->subject)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /** @return array{id: int, type: string, label: string}|null */
    private function subjectSummary(?Model $subject): ?array
    {
        return match (true) {
            $subject instanceof Task => [
                'id' => $subject->id,
                'type' => 'task',
                'label' => $subject->title,
            ],
            $subject instanceof Sprint => [
                'id' => $subject->id,
                'type' => 'sprint',
                'label' => $subject->name,
            ],
            default => null,
        };
    }
}
