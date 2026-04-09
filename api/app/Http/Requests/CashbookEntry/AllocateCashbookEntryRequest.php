<?php

namespace App\Http\Requests\CashbookEntry;

use Illuminate\Foundation\Http\FormRequest;

class AllocateCashbookEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('allocate', $this->route('cashbookEntry'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'uuid', 'exists:invoices,id'],
            'unit_id'    => ['required', 'uuid', 'exists:units,id'],
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
            'invoice_id.required' => 'The invoice is required for allocation.',
            'invoice_id.uuid'     => 'The invoice ID must be a valid UUID.',
            'invoice_id.exists'   => 'The selected invoice does not exist.',
            'unit_id.required'    => 'The unit is required for allocation.',
            'unit_id.uuid'        => 'The unit ID must be a valid UUID.',
            'unit_id.exists'      => 'The selected unit does not exist.',
        ];
    }
}
