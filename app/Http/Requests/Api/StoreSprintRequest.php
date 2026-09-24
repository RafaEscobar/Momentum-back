<?php

namespace App\Http\Requests\Api;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSprintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('create', [Sprint::class, $this->route('project')]);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Sprint::class)->where(
                    fn (Builder $query) => $query->where('project_id', $project->getKey())
                ),
            ],
            'goal' => ['nullable', 'string', 'max:10000'],
            'start_date' => ['nullable', Rule::date()->format('Y-m-d')],
            'end_date' => ['nullable', Rule::date()->format('Y-m-d'), 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::enum(SprintStatus::class)],
        ];
    }
}
