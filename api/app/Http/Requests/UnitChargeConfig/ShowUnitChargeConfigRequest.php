<?php

namespace App\Http\Requests\UnitChargeConfig;

use App\Models\UnitChargeConfig;
use Illuminate\Foundation\Http\FormRequest;

class ShowUnitChargeConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', [UnitChargeConfig::class, $this->route('unit'), $this->route('chargeConfig')]);
    }

    public function rules(): array
    {
        return [];
    }
}
