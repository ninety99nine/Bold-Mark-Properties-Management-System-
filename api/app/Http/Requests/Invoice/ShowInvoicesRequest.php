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
            'estate_id'      => ['sometimes', 'uuid', 'exists:estates,id'],
            'status'         => ['sometimes', 'string'],
            'charge_type_id' => ['sometimes', 'uuid', 'exists:charge_types,id'],
        ];
    }
}
