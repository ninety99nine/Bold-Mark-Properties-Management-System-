<?php

namespace App\Http\Requests\UnitLedgerConfig;

use App\Models\UnitLedgerConfig;
use Illuminate\Foundation\Http\FormRequest;

class CreateUnitLedgerConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [UnitLedgerConfig::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'ledger_id' => ['required', 'uuid', 'exists:ledgers,id'],
            'amount'         => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ledger_id.required' => 'The ledger is required.',
            'ledger_id.uuid'     => 'The ledger ID must be a valid UUID.',
            'ledger_id.exists'   => 'The selected ledger does not exist.',
            'amount.required'         => 'The amount is required.',
            'amount.numeric'          => 'The amount must be a number.',
            'amount.min'              => 'The amount must be at least 0.',
        ];
    }
}
