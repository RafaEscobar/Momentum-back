<?php

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /** @var list<int> */
    public const STORY_POINT_OPTIONS = [1, 2, 3, 5, 8, 13];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sprint_id',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'story_points',
        'position',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => ProjectPriority::class,
            'status' => TaskStatus::class,
            'story_points' => 'integer',
            'position' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Sprint, $this> */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /** @return HasMany<ChecklistItem, $this> */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->statusIs($status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->priorityIs($priority))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->typeIs($type))
            ->when($filters['sprint_id'] ?? null, fn (Builder $query, int $sprintId) => $query->inSprint($sprintId))
            ->when($filters['tag_id'] ?? null, fn (Builder $query, int $tagId) => $query->withTag($tagId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search));
    }

    /** @param Builder<Task> $query */
    public function scopeStatusIs(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** @param Builder<Task> $query */
    public function scopePriorityIs(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /** @param Builder<Task> $query */
    public function scopeTypeIs(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** @param Builder<Task> $query */
    public function scopeInSprint(Builder $query, int $sprintId): Builder
    {
        return $query->where('sprint_id', $sprintId);
    }

    /** @param Builder<Task> $query */
    public function scopeWithTag(Builder $query, int $tagId): Builder
    {
        return $query->whereHas('tags', fn (Builder $query) => $query->whereKey($tagId));
    }

    /** @param Builder<Task> $query */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($search): void {
            $query->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
