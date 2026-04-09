<?php

namespace App\Http\Requests\UnitTenant;

use App\Models\UnitTenant;
use Illuminate\Foundation\Http\FormRequest;

class MoveOutUnitTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moveOut', [UnitTenant::class, $this->route('unit'), $this->route('unitTenant')]);
    }

    public function rules(): array
    {
        return [
            'move_out_date'   => ['nullable', 'date'],
            'move_out_reason' => ['nullable', 'string', 'max:255'],
            'move_out_notes'  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
