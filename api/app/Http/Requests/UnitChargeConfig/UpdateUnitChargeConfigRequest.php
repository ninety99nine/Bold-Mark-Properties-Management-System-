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
            'charge_type_id' => ['sometimes', 'uuid', 'exists:charge_types,id'],
            'amount'         => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'charge_type_id.uuid'   => 'The charge type ID must be a valid UUID.',
            'charge_type_id.exists' => 'The selected charge type does not exist.',
        ];
    }
}
