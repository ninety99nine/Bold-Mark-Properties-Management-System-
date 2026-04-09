<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class DeleteInvoicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', Invoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_ids'   => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['uuid'],
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
            'invoice_ids.required' => 'At least one invoice ID is required.',
            'invoice_ids.array'    => 'The invoice IDs must be provided as an array.',
            'invoice_ids.min'      => 'At least one invoice ID must be provided.',
            'invoice_ids.*.uuid'   => 'Each invoice ID must be a valid UUID.',
        ];
    }
}
