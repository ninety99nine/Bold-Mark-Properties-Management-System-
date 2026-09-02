<?php

namespace App\Http\Requests\UnitLedgerConfig;

use App\Models\UnitLedgerConfig;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitLedgerConfigsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', [UnitLedgerConfig::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'ledger_config_ids'   => ['required', 'array', 'min:1'],
            'ledger_config_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'ledger_config_ids.required' => 'At least one ledger config ID is required.',
            'ledger_config_ids.min'      => 'At least one ledger config ID must be provided.',
            'ledger_config_ids.*.uuid'   => 'Each ledger config ID must be a valid UUID.',
        ];
    }
}
