<?php

namespace App\Models;

use App\Enums\SprintStatus;
use App\Enums\TaskStatus;
use Database\Factories\SprintFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    /** @use HasFactory<SprintFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'goal',
        'start_date',
        'end_date',
        'status',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => SprintStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @param Builder<Sprint> $query */
    public function scopeWithPointTotals(Builder $query): Builder
    {
        return $query
            ->withSum('tasks as planned_points', 'story_points')
            ->withSum([
                'tasks as completed_points' => fn (Builder $query) => $query->where('status', TaskStatus::Done),
            ], 'story_points');
    }

    public function freshWithPointTotals(): self
    {
        return self::query()->withPointTotals()->findOrFail($this->getKey());
    }

    public function plannedPoints(): int
    {
        return (int) ($this->getAttribute('planned_points') ?? 0);
    }

    public function completedPoints(): int
    {
        return (int) ($this->getAttribute('completed_points') ?? 0);
    }

    public function progressPercentage(): int
    {
        return Project::calculateProgress($this->completedPoints(), $this->plannedPoints());
    }
}
