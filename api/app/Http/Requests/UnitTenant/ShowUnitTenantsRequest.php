<?php

namespace App\Http\Requests\UnitTenant;

use App\Models\UnitTenant;
use Illuminate\Foundation\Http\FormRequest;

class ShowUnitTenantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [UnitTenant::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [];
    }
}
