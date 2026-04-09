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

    public function rules(): array
    {
        return [
            'type'      => ['nullable', Rule::in(EstateType::values())],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
