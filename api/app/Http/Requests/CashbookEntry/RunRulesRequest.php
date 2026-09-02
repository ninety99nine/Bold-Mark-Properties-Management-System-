<?php

namespace App\Http\Requests\CashbookEntry;

use App\Models\CashbookEntry;
use Illuminate\Foundation\Http\FormRequest;

class RunRulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('autoAllocate', CashbookEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => ['nullable', 'uuid', 'exists:bank_accounts,id'],
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
            'bank_account_id.uuid'   => 'The bank account ID must be a valid UUID.',
            'bank_account_id.exists' => 'The selected bank account does not exist.',
        ];
    }
}
