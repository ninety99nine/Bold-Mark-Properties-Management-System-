<?php

namespace App\Http\Requests\Occupant;

use App\Models\Occupant;
use Illuminate\Foundation\Http\FormRequest;

class ShowOccupantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [Occupant::class, $this->route('unit')]);
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
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
