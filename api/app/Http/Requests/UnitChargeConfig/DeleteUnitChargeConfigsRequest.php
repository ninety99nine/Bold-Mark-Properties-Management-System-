<?php

namespace App\Http\Requests\UnitChargeConfig;

use App\Models\UnitChargeConfig;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitChargeConfigsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', [UnitChargeConfig::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'charge_config_ids'   => ['required', 'array', 'min:1'],
            'charge_config_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'charge_config_ids.required' => 'At least one charge config ID is required.',
            'charge_config_ids.min'      => 'At least one charge config ID must be provided.',
            'charge_config_ids.*.uuid'   => 'Each charge config ID must be a valid UUID.',
        ];
    }
}
