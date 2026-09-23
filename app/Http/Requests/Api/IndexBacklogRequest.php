<?php

namespace App\Http\Requests\Api;

use App\Enums\ProjectPriority;
use App\Enums\TaskType;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexBacklogRequest extends FormRequest
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
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'priority' => ['sometimes', Rule::enum(ProjectPriority::class)],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'search' => ['sometimes', 'string', 'max:255'],
            'tag_ids' => ['sometimes', 'array', 'max:50'],
            'tag_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge(['search' => trim($this->string('search')->toString())]);
        }
    }
}
