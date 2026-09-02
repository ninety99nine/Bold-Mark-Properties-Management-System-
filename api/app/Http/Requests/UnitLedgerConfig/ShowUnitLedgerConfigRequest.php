<?php

namespace App\Http\Requests\UnitLedgerConfig;

use App\Models\UnitLedgerConfig;
use Illuminate\Foundation\Http\FormRequest;

class ShowUnitLedgerConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', [UnitLedgerConfig::class, $this->route('unit'), $this->route('ledgerConfig')]);
    }

    public function rules(): array
    {
        return [];
    }
}
