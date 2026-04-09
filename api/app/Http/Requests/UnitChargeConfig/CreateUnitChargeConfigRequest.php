<?php

namespace App\Http\Requests\UnitChargeConfig;

use App\Models\UnitChargeConfig;
use Illuminate\Foundation\Http\FormRequest;

class CreateUnitChargeConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [UnitChargeConfig::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'charge_type_id' => ['required', 'uuid', 'exists:charge_types,id'],
            'amount'         => ['required', 'numeric', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'charge_type_id.required' => 'The charge type is required.',
            'charge_type_id.uuid'     => 'The charge type ID must be a valid UUID.',
            'charge_type_id.exists'   => 'The selected charge type does not exist.',
            'amount.required'         => 'The amount is required.',
            'amount.numeric'          => 'The amount must be a number.',
            'amount.min'              => 'The amount must be at least 0.',
        ];
    }
}
