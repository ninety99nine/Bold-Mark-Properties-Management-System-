<?php

namespace App\Http\Requests\UnitChargeConfig;

use App\Models\UnitChargeConfig;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitChargeConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [UnitChargeConfig::class, $this->route('unit'), $this->route('chargeConfig')]);
    }

    public function rules(): array
    {
        return [
            'ledger_id' => ['sometimes', 'uuid', 'exists:ledgers,id'],
            'amount'         => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ledger_id.uuid'   => 'The ledger ID must be a valid UUID.',
            'ledger_id.exists' => 'The selected ledger does not exist.',
        ];
    }
}
