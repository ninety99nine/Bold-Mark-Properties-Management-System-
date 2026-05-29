<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class FlushOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'targets'             => ['required', 'array', 'min:1'],
            'targets.*'           => ['required', 'string', 'in:estates,units,owners,tenants,invoices,cashbook_entries,compliance_checklists,users'],
            'keep_user_ids'       => ['sometimes', 'array'],
            'keep_user_ids.*'     => ['sometimes', 'uuid'],
            'confirmation'        => ['required', 'string', 'in:DELETE'],
        ];
    }

    public function messages(): array
    {
        return [
            'targets.required'       => 'Please select at least one data category to delete.',
            'targets.min'            => 'Please select at least one data category to delete.',
            'targets.*.in'           => 'Invalid data category selected.',
            'confirmation.in'        => 'You must type DELETE to confirm.',
            'confirmation.required'  => 'Confirmation is required.',
        ];
    }
}
