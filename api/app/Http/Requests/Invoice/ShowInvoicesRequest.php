<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class ShowInvoicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Invoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search'         => ['nullable', 'string', 'max:100'],
            'unit_id'        => ['sometimes', 'uuid', 'exists:units,id'],
            'community_id'      => ['sometimes', 'uuid', 'exists:communities,id'],
            'status'         => ['sometimes', 'string'],
            'ledger_id' => ['sometimes', 'uuid', 'exists:ledgers,id'],
            'billed_to_type' => ['sometimes', 'string'],
            'country'        => ['nullable', 'string', 'max:3'],
        ];
    }
}
