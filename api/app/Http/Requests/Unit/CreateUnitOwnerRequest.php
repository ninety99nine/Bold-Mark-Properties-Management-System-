<?php

namespace App\Http\Requests\Unit;

use App\Enums\OwnerEntityType;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUnitOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'full_name'         => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'landline'          => ['nullable', 'string', 'max:30'],
            'id_number'         => ['nullable', 'string', 'max:50'],
            'entity_type'       => ['nullable', Rule::in(OwnerEntityType::values())],
            'contact2_name'     => ['nullable', 'string', 'max:255'],
            'contact2_email'    => ['nullable', 'email', 'max:255'],
            'contact2_phone'    => ['nullable', 'string', 'max:30'],
            'contact2_landline' => ['nullable', 'string', 'max:30'],
            'user_display_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
