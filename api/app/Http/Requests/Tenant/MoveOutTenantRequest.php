<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class MoveOutTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moveOut', [Tenant::class, $this->route('unit'), $this->route('tenant')]);
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
