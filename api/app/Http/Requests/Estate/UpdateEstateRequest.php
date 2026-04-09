<?php

namespace App\Http\Requests\Estate;

use App\Enums\EstateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('estate'));
    }

    public function rules(): array
    {
        return [
            'name'                 => ['sometimes', 'string', 'max:255'],
            'type'                 => ['sometimes', Rule::in(EstateType::values())],
            'address'              => ['sometimes', 'nullable', 'string', 'max:500'],
            'default_levy_amount'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'default_rent_amount'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'billing_day'          => ['sometimes', 'nullable', 'integer', 'min:1', 'max:28'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The estate type must be one of: ' . implode(', ', EstateType::values()) . '.',
        ];
    }
}
