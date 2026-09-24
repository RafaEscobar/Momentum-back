<?php

namespace App\Http\Requests\Api;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSprintRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique(Sprint::class)->where(
                    fn (Builder $query) => $query->where('project_id', $project->getKey())
                )->ignore($sprint),
            ],
            'goal' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'start_date' => ['sometimes', 'nullable', Rule::date()->format('Y-m-d')],
            'end_date' => ['sometimes', 'nullable', Rule::date()->format('Y-m-d')],
            'status' => ['sometimes', Rule::enum(SprintStatus::class)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                /** @var Sprint $sprint */
                $sprint = $this->route('sprint');
                $input = $this->all();
                $startDate = array_key_exists('start_date', $input)
                    ? $input['start_date']
                    : $sprint->start_date?->toDateString();
                $endDate = array_key_exists('end_date', $input)
                    ? $input['end_date']
                    : $sprint->end_date?->toDateString();

                if ($startDate && $endDate && $endDate < $startDate) {
                    $validator->errors()->add('end_date', 'The end date must be after or equal to the start date.');
                }
            },
        ];
    }
}
