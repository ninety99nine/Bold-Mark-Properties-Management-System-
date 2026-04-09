<?php

namespace App\Http\Requests\ChargeType;

use App\Enums\ChargeTypeAppliesTo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChargeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('chargeType'));
    }

    public function rules(): array
    {
        return [
            'code'         => ['sometimes', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'name'         => ['sometimes', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'applies_to'   => ['sometimes', Rule::in(ChargeTypeAppliesTo::values())],
            'is_recurring' => ['sometimes', 'boolean'],
            'sort_order'   => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex'     => 'The code may only contain uppercase letters, numbers, and underscores (e.g. GENERATOR_FEE).',
            'applies_to.in'  => 'The applies_to must be one of: ' . implode(', ', ChargeTypeAppliesTo::values()) . '.',
        ];
    }
}
