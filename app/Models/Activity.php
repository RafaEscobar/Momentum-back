<?php

namespace App\Models;

use App\Enums\ActivityType;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'description',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return Builder<Activity> */
    public function prunable(): Builder
    {
        return static::query()->where(
            'created_at',
            '<=',
            now()->subDays((int) config('security.activity_retention_days', 365)),
        );
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
