<?php

namespace App\Models;

use Database\Factories\GeneralNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralNote extends Model
{
    /** @use HasFactory<GeneralNoteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'content',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
