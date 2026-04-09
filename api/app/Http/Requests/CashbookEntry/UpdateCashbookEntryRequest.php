<?php

namespace App\Http\Requests\CashbookEntry;

use App\Enums\CashbookEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashbookEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cashbookEntry'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date'        => ['sometimes', 'date'],
            'type'        => ['sometimes', Rule::in(CashbookEntryType::values())],
            'description' => ['sometimes', 'string', 'max:500'],
            'amount'      => ['sometimes', 'numeric', 'min:0.01'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'unit_id'     => ['nullable', 'uuid', 'exists:units,id'],
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
            'date.date'          => 'The transaction date must be a valid date.',
            'type.in'            => 'The entry type must be one of: ' . implode(', ', CashbookEntryType::values()) . '.',
            'description.string' => 'The description must be a string.',
            'description.max'    => 'The description may not exceed 500 characters.',
            'amount.numeric'     => 'The amount must be a number.',
            'amount.min'         => 'The amount must be greater than zero.',
            'notes.max'          => 'The notes may not exceed 1000 characters.',
            'unit_id.uuid'       => 'The unit ID must be a valid UUID.',
            'unit_id.exists'     => 'The selected unit does not exist.',
        ];
    }
}
