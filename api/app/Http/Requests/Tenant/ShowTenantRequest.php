<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class ShowTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', [Tenant::class, $this->route('unit'), $this->route('tenant')]);
    }

    public function rules(): array
    {
        return [];
    }
}
