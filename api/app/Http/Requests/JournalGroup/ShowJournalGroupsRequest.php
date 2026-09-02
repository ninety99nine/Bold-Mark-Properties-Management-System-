<?php

namespace App\Http\Requests\JournalGroup;

use App\Models\JournalGroup;
use Illuminate\Foundation\Http\FormRequest;

class ShowJournalGroupsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [JournalGroup::class, $this->route('community')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
