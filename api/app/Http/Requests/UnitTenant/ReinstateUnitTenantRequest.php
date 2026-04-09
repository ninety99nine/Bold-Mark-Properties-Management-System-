<?php

namespace App\Http\Requests\UnitTenant;

use App\Models\UnitTenant;
use Illuminate\Foundation\Http\FormRequest;

class ReinstateUnitTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reinstate', [UnitTenant::class, $this->route('unit'), $this->route('unitTenant')]);
    }

    public function rules(): array
    {
        return [];
    }
}
