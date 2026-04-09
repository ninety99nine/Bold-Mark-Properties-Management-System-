<?php

namespace App\Http\Requests\UnitChargeConfig;

use App\Models\UnitChargeConfig;
use Illuminate\Foundation\Http\FormRequest;

class ShowUnitChargeConfigsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [UnitChargeConfig::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [];
    }
}
