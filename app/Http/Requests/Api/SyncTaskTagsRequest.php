<?php

namespace App\Http\Requests\Api;

use App\Models\Tag;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SyncTaskTagsRequest extends FormRequest
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
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tag_ids' => ['present', 'array', 'max:50'],
            'tag_ids.*' => [
                'integer',
                'distinct:strict',
                Rule::exists(Tag::class, 'id')->where(
                    fn (Builder $query) => $query->where('user_id', $this->user()->getKey())
                ),
            ],
        ];
    }
}
