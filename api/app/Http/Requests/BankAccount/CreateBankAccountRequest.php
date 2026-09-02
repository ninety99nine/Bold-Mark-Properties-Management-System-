<?php

namespace App\Http\Requests\BankAccount;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBankAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', BankAccount::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'bank_name'      => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'branch_code'    => ['nullable', 'string', 'max:50'],
            'branch_name'    => ['nullable', 'string', 'max:255'],
            'integration'    => ['nullable', 'string', 'max:255'],
            'type'           => ['required', Rule::in(BankAccountType::values())],
            'balance'         => ['nullable', 'numeric'],
            'balance_as_at'   => ['nullable', 'date'],
            'is_active'       => ['nullable', 'boolean'],
            'is_default'      => ['nullable', 'boolean'],
            'tenant_billing_account' => ['nullable', 'boolean'],
            'opening_balance' => ['nullable', 'numeric'],
            'community_id'    => ['nullable', 'uuid', 'exists:communities,id'],
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
            'name.required'    => 'The bank account name is required.',
            'name.string'      => 'The bank account name must be a string.',
            'name.max'         => 'The bank account name must not exceed 255 characters.',
            'type.required'    => 'The bank account type is required.',
            'type.in'          => 'The bank account type is invalid.',
            'balance.numeric'  => 'The balance must be a number.',
            'community_id.uuid'   => 'The community ID must be a valid UUID.',
            'community_id.exists' => 'The selected community does not exist.',
        ];
    }
}
