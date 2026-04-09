<?php

namespace App\Http\Requests\CashbookEntry;

use Illuminate\Foundation\Http\FormRequest;

class DeallocateCashbookEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('deallocate', $this->route('cashbookEntry'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
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
            'reason.required' => 'A reason for removing this payment is required.',
            'reason.max'      => 'The reason may not exceed 500 characters.',
        ];
    }
}
