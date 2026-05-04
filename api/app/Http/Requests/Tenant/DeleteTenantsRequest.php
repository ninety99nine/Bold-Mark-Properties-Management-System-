<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class DeleteTenantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', [Tenant::class, $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'tenant_ids'   => ['required', 'array', 'min:1'],
            'tenant_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_ids.required' => 'At least one unit tenant ID is required.',
            'tenant_ids.min'      => 'At least one unit tenant ID must be provided.',
            'tenant_ids.*.uuid'   => 'Each unit tenant ID must be a valid UUID.',
        ];
    }
}
