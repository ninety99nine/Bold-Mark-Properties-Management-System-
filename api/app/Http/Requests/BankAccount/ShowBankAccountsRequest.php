<?php

namespace App\Http\Requests\BankAccount;

use App\Models\BankAccount;
use Illuminate\Foundation\Http\FormRequest;

class ShowBankAccountsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', BankAccount::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'community_id' => ['sometimes', 'uuid', 'exists:communities,id'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
