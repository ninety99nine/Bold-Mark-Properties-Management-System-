<?php

namespace App\Http\Requests\CashbookEntry;

use App\Models\CashbookEntry;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCashbookEntriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', CashbookEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entry_ids'   => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['uuid'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_ids.required' => 'At least one entry ID is required.',
            'entry_ids.array'    => 'The entry IDs must be provided as an array.',
            'entry_ids.min'      => 'At least one entry ID must be provided.',
            'entry_ids.*.uuid'   => 'Each entry ID must be a valid UUID.',
        ];
    }
}
