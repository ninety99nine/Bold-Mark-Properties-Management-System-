<?php

namespace App\Http\Requests\ChargeType;

use App\Enums\ChargeTypeAppliesTo;
use App\Models\ChargeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateChargeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ChargeType::class);
    }

    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'applies_to'  => ['required', Rule::in(ChargeTypeAppliesTo::values())],
            'is_recurring' => ['required', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'       => 'The charge type code is required.',
            'code.regex'          => 'The code may only contain uppercase letters, numbers, and underscores (e.g. GENERATOR_FEE).',
            'name.required'       => 'The charge type name is required.',
            'applies_to.required' => 'The applies_to field is required.',
            'applies_to.in'       => 'The applies_to must be one of: ' . implode(', ', ChargeTypeAppliesTo::values()) . '.',
            'is_recurring.required' => 'The is_recurring field is required.',
        ];
    }
}
