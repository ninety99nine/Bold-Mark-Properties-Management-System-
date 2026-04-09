<?php

namespace App\Http\Requests\UnitChargeConfig;

use App\Models\UnitChargeConfig;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitChargeConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', [UnitChargeConfig::class, $this->route('unit'), $this->route('chargeConfig')]);
    }

    public function rules(): array
    {
        return [];
    }
}
