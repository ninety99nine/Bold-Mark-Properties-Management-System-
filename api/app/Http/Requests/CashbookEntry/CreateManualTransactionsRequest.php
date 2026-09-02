<?php

namespace App\Http\Requests\CashbookEntry;

use App\Models\CashbookEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateManualTransactionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', CashbookEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The bank account must belong to the community in the route — otherwise it
     * fails the scoped exists check and surfaces the WeConnectU-style
     * "Bank account not selected" error.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => [
                'required',
                'uuid',
                Rule::exists('bank_accounts', 'id')->where(
                    fn ($query) => $query->where('community_id', $this->route('community')->id)
                ),
            ],
            'transactions'               => ['required', 'array', 'min:1'],
            'transactions.*.date'        => ['required', 'date'],
            'transactions.*.description' => ['required', 'string', 'max:500'],
            'transactions.*.amount'      => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'transactions.*.type'        => ['required', Rule::in(['income', 'expense'])],
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
            'bank_account_id.required'      => 'The bank account was not selected for this cashbook.',
            'bank_account_id.uuid'          => 'The bank account was not selected for this cashbook.',
            'bank_account_id.exists'        => 'The bank account was not selected for this cashbook.',
            'transactions.required'         => 'At least one transaction line is required.',
            'transactions.min'              => 'At least one transaction line is required.',
            'transactions.*.date.required'        => 'The transaction date is required.',
            'transactions.*.date.date'            => 'The transaction date must be a valid date.',
            'transactions.*.description.required' => 'The description is required.',
            'transactions.*.description.max'      => 'The description may not exceed 500 characters.',
            'transactions.*.amount.required'      => 'The amount is required.',
            'transactions.*.amount.numeric'       => 'The amount must be a number.',
            'transactions.*.amount.min'           => 'The amount must be greater than zero.',
            'transactions.*.type.required'        => 'Select whether the transaction is income or expense.',
            'transactions.*.type.in'              => 'The transaction type must be income or expense.',
        ];
    }
}
