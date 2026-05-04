<?php

namespace App\Http\Requests\Estate;

use App\Enums\EstateType;
use App\Models\Estate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowEstatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Estate::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'type'      => ['nullable', Rule::in(EstateType::values())],
            'is_active' => ['nullable', 'boolean'],
            'country'   => ['nullable', 'string', 'max:3'],
        ];
    }
}
