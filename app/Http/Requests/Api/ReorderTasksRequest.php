<?php

namespace App\Http\Requests\Api;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReorderTasksRequest extends FormRequest
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
            'tasks' => ['required', 'array', 'min:1', 'max:200'],
            'tasks.*' => ['array:id,status,position'],
            'tasks.*.id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'tasks.*.status' => ['required', Rule::enum(TaskStatus::class)],
            'tasks.*.position' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
