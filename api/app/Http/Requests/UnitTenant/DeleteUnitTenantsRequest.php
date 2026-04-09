<?php

namespace App\Http\Requests\UnitTenant;

use App\Models\UnitTenant;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitTenantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', [UnitTenant::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'unit_tenant_ids'   => ['required', 'array', 'min:1'],
            'unit_tenant_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_tenant_ids.required' => 'At least one unit tenant ID is required.',
            'unit_tenant_ids.min'      => 'At least one unit tenant ID must be provided.',
            'unit_tenant_ids.*.uuid'   => 'Each unit tenant ID must be a valid UUID.',
        ];
    }
}
