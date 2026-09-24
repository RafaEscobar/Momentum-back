<?php

namespace App\Http\Requests\Api;

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Rules\SprintBelongsToProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('task'));

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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'priority' => ['sometimes', Rule::enum(ProjectPriority::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'story_points' => ['sometimes', 'nullable', 'integer', Rule::in(Task::STORY_POINT_OPTIONS)],
            'sprint_id' => ['sometimes', 'nullable', 'integer', 'min:1', new SprintBelongsToProject($project)],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
