<?php

namespace App\Http\Requests\Unit;

use App\Enums\OwnerEntityType;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'full_name'         => ['sometimes', 'string', 'max:255'],
            'email'             => ['sometimes', 'email', 'max:255'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:30'],
            'landline'          => ['sometimes', 'nullable', 'string', 'max:30'],
            'id_number'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'entity_type'       => ['sometimes', 'nullable', Rule::in(OwnerEntityType::values())],
            'contact2_name'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact2_email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact2_phone'    => ['sometimes', 'nullable', 'string', 'max:30'],
            'contact2_landline' => ['sometimes', 'nullable', 'string', 'max:30'],
            'user_display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_verified'     => ['sometimes', 'boolean'],
        ];
    }
}
