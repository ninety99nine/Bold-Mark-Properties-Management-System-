<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class DeleteTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', [Tenant::class, $this->route('unit'), $this->route('tenant')]);
    }

    public function rules(): array
    {
        return [];
    }
}
