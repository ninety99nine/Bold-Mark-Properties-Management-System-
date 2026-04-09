<?php

namespace App\Http\Requests\UnitTenant;

use App\Models\UnitTenant;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', [UnitTenant::class, $this->route('unit'), $this->route('unitTenant')]);
    }

    public function rules(): array
    {
        return [];
    }
}
