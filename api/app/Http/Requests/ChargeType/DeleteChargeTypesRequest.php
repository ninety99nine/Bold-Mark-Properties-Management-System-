<?php

namespace App\Http\Requests\ChargeType;

use App\Models\ChargeType;
use Illuminate\Foundation\Http\FormRequest;

class DeleteChargeTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', ChargeType::class);
    }

    public function rules(): array
    {
        return [
            'charge_type_ids'   => ['required', 'array', 'min:1'],
            'charge_type_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'charge_type_ids.required' => 'At least one charge type ID is required.',
            'charge_type_ids.min'      => 'At least one charge type ID must be provided.',
            'charge_type_ids.*.uuid'   => 'Each charge type ID must be a valid UUID.',
        ];
    }
}
