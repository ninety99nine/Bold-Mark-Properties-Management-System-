<?php

namespace App\Http\Requests\Estate;

use App\Enums\EstateType;
use App\Models\Estate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEstateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Estate::class);
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'type'                 => ['required', Rule::in(EstateType::values())],
            'address'              => ['nullable', 'string', 'max:500'],
            'default_levy_amount'  => ['nullable', 'numeric', 'min:0'],
            'default_rent_amount'  => ['nullable', 'numeric', 'min:0'],
            'billing_day'          => ['nullable', 'integer', 'min:1', 'max:28'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'The estate name is required.',
            'name.max'       => 'The estate name may not exceed 255 characters.',
            'type.required'  => 'The estate type is required.',
            'type.in'        => 'The estate type must be one of: ' . implode(', ', EstateType::values()) . '.',
        ];
    }
}
