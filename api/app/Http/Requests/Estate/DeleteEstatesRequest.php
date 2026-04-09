<?php

namespace App\Http\Requests\Estate;

use App\Models\Estate;
use Illuminate\Foundation\Http\FormRequest;

class DeleteEstatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', Estate::class);
    }

    public function rules(): array
    {
        return [
            'estate_ids'   => ['required', 'array', 'min:1'],
            'estate_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'estate_ids.required' => 'At least one estate ID is required.',
            'estate_ids.min'      => 'At least one estate ID must be provided.',
            'estate_ids.*.uuid'   => 'Each estate ID must be a valid UUID.',
        ];
    }
}
