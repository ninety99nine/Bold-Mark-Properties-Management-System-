<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class ShowOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * The occupant is resolved from the authenticated user's organisation — no route param needed.
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
