<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ShowTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * The tenant is resolved from the authenticated user's organisation — no route param needed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
