<?php

namespace App\Http\Requests\Api;

use App\Models\Project;
use App\Models\Task;
use App\Rules\SprintBelongsToProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowBoardRequest extends FormRequest
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
            'sprint_id' => ['sometimes', 'integer', new SprintBelongsToProject($project)],
        ];
    }
}
