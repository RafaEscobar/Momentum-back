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

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('create', [Task::class, $this->route('project')]);

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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'priority' => ['sometimes', Rule::enum(ProjectPriority::class)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'story_points' => ['nullable', 'integer', Rule::in(Task::STORY_POINT_OPTIONS)],
            'sprint_id' => ['nullable', 'integer', new SprintBelongsToProject($project)],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
