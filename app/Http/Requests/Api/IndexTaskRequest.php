<?php

namespace App\Http\Requests\Api;

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Rules\SprintBelongsToProject;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        Gate::authorize('viewAny', [Task::class, $this->route('project')]);

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
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(ProjectPriority::class)],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'sprint_id' => ['sometimes', 'integer', new SprintBelongsToProject($project)],
            'tag_id' => [
                'sometimes',
                'integer',
                Rule::exists(Tag::class, 'id')->where(
                    fn (Builder $query) => $query->where('user_id', $this->user()->getKey())
                ),
            ],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge(['search' => trim($this->string('search')->toString())]);
        }
    }
}
