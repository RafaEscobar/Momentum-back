<?php

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'color',
        'icon',
        'start_date',
        'target_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => ProjectPriority::class,
            'start_date' => 'date',
            'target_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Sprint, $this> */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /** @return HasMany<ProjectNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function progress(): int
    {
        return self::calculateProgress($this->completedStoryPoints(), $this->totalStoryPoints());
    }

    /** @param Builder<Project> $query */
    public function scopeWithTaskPointTotals(Builder $query): Builder
    {
        return $query
            ->withSum('tasks as total_story_points', 'story_points')
            ->withSum([
                'tasks as completed_story_points' => fn (Builder $query) => $query->where('status', TaskStatus::Done),
            ], 'story_points');
    }

    public function freshWithTaskPointTotals(): self
    {
        return self::query()->withTaskPointTotals()->findOrFail($this->getKey());
    }

    public function totalStoryPoints(): int
    {
        return (int) ($this->getAttribute('total_story_points') ?? 0);
    }

    public function completedStoryPoints(): int
    {
        return (int) ($this->getAttribute('completed_story_points') ?? 0);
    }

    public static function calculateProgress(int $completedStoryPoints, int $totalStoryPoints): int
    {
        if ($totalStoryPoints <= 0) {
            return 0;
        }

        return (int) round(($completedStoryPoints / $totalStoryPoints) * 100);
    }
}
