<?php

namespace App\Http\Requests\CashbookEntry;

use App\Models\CashbookEntry;
use Illuminate\Foundation\Http\FormRequest;

class ShowCashbookTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CashbookEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => ['nullable', 'uuid', 'exists:bank_accounts,id'],
            'from'            => ['nullable', 'date'],
            'to'              => ['nullable', 'date'],
            'search'          => ['nullable', 'string', 'max:255'],
            'hide_allocated'  => ['nullable', 'boolean'],
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
            'bank_account_id.uuid'   => 'The bank account ID must be a valid UUID.',
            'bank_account_id.exists' => 'The selected bank account does not exist.',
            'from.date'              => 'The from date must be a valid date.',
            'to.date'                => 'The to date must be a valid date.',
            'search.max'             => 'The search term may not exceed 255 characters.',
            'hide_allocated.boolean' => 'The hide allocated flag must be true or false.',
        ];
    }
}
