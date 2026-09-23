<?php

namespace App\Rules;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\PotentiallyTranslatedString;

class SprintBelongsToProject implements ValidationRule
{
    public function __construct(private readonly Project $project) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Schema::hasTable('sprints') || ! DB::table('sprints')
            ->where('id', $value)
            ->where('project_id', $this->project->getKey())
            ->exists()) {
            $fail('The selected sprint is invalid.');
        }
    }
}
