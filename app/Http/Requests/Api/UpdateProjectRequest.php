<?php

namespace App\Http\Requests\Api;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('project'));

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'priority' => ['sometimes', Rule::enum(ProjectPriority::class)],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'start_date' => ['sometimes', 'nullable', Rule::date()->format('Y-m-d')],
            'target_date' => ['sometimes', 'nullable', Rule::date()->format('Y-m-d')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'target_date'])) {
                    return;
                }

                /** @var Project $project */
                $project = $this->route('project');
                $input = $this->all();
                $startDate = array_key_exists('start_date', $input)
                    ? $input['start_date']
                    : $project->start_date?->toDateString();
                $targetDate = array_key_exists('target_date', $input)
                    ? $input['target_date']
                    : $project->target_date?->toDateString();

                if ($startDate && $targetDate && $targetDate < $startDate) {
                    $validator->errors()->add('target_date', 'The target date must be after or equal to the start date.');
                }
            },
        ];
    }
}
