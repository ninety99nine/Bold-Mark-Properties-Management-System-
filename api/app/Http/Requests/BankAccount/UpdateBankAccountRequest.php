<?php

namespace App\Http\Requests\BankAccount;

use App\Enums\BankAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bankAccount'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'bank_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'branch_code'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'branch_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'integration'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'type'           => ['sometimes', Rule::in(BankAccountType::values())],
            'balance'         => ['sometimes', 'nullable', 'numeric'],
            'balance_as_at'   => ['sometimes', 'nullable', 'date'],
            'is_active'       => ['sometimes', 'boolean'],
            'is_default'      => ['sometimes', 'boolean'],
            'tenant_billing_account' => ['sometimes', 'boolean'],
            'opening_balance' => ['sometimes', 'nullable', 'numeric'],
            'community_id'    => ['sometimes', 'nullable', 'uuid', 'exists:communities,id'],
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
            'name.string'      => 'The bank account name must be a string.',
            'name.max'         => 'The bank account name must not exceed 255 characters.',
            'type.in'          => 'The bank account type is invalid.',
            'balance.numeric'  => 'The balance must be a number.',
            'community_id.uuid'   => 'The community ID must be a valid UUID.',
            'community_id.exists' => 'The selected community does not exist.',
        ];
    }
}
