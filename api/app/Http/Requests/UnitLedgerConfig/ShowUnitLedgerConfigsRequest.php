<?php

namespace App\Http\Requests\UnitLedgerConfig;

use App\Models\UnitLedgerConfig;
use Illuminate\Foundation\Http\FormRequest;

class ShowUnitLedgerConfigsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [UnitLedgerConfig::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [];
    }
}
