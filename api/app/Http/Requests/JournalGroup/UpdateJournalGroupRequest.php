<?php

namespace App\Http\Requests\JournalGroup;

use App\Models\JournalGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJournalGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', [JournalGroup::class, $this->route('community'), $this->route('journalGroup')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('journal_groups', 'name')
                    ->where('community_id', $this->route('community')->id)
                    ->ignore($this->route('journalGroup')->id),
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A journal group with this name already exists.',
        ];
    }
}
