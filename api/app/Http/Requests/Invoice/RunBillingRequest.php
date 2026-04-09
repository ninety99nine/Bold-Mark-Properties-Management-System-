<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class RunBillingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estate_id'      => ['required', 'uuid', 'exists:estates,id'],
            'billing_period' => ['required', 'date_format:Y-m'],
            'dry_run'        => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estate_id.required'         => 'The estate is required.',
            'estate_id.uuid'             => 'The estate ID must be a valid UUID.',
            'estate_id.exists'           => 'The selected estate does not exist.',
            'billing_period.required'    => 'The billing period is required.',
            'billing_period.date_format' => 'The billing period must be in Y-m format (e.g. 2026-04).',
            'dry_run.boolean'            => 'The dry run flag must be true or false.',
        ];
    }
}
