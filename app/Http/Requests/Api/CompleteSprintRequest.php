<?php

namespace App\Http\Requests\Api;

use App\Enums\SprintStatus;
use App\Enums\UnfinishedTaskAction;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompleteSprintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('sprint'));

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
        /** @var Sprint $sprint */
        $sprint = $this->route('sprint');

        return [
            'unfinished_action' => ['required', Rule::enum(UnfinishedTaskAction::class)],
            'include_completed_tasks' => ['sometimes', 'boolean'],
            'next_sprint_id' => [
                Rule::requiredIf($this->input('unfinished_action') === UnfinishedTaskAction::NextSprint->value),
                Rule::prohibitedIf($this->input('unfinished_action') !== UnfinishedTaskAction::NextSprint->value),
                'integer',
                Rule::exists(Sprint::class, 'id')->where(
                    fn (Builder $query) => $query
                        ->where('project_id', $project->getKey())
                        ->where('id', '<>', $sprint->getKey())
                        ->where('status', '<>', SprintStatus::Completed->value)
                ),
            ],
        ];
    }
}
