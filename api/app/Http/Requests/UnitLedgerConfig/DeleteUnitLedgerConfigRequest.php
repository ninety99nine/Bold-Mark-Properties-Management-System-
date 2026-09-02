<?php

namespace App\Http\Requests\UnitLedgerConfig;

use App\Models\UnitLedgerConfig;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitLedgerConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', [UnitLedgerConfig::class, $this->route('unit'), $this->route('ledgerConfig')]);
    }

    public function rules(): array
    {
        return [];
    }
}
