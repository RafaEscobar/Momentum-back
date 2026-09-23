<?php

namespace App\Models;

use Database\Factories\ProjectNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNote extends Model
{
    /** @use HasFactory<ProjectNoteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'content',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
