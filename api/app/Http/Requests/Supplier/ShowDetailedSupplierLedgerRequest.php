<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class ShowDetailedSupplierLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\Supplier::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'date_from'      => ['nullable', 'date'],
            'date_to'        => ['nullable', 'date'],
            'supplier_ids'   => ['nullable', 'array'],
            'supplier_ids.*' => ['uuid'],
            'group_ids'      => ['nullable', 'array'],
            'group_ids.*'    => ['uuid'],
            'all_suppliers'  => ['nullable'],
            'hide_zero'      => ['nullable'],
        ];
    }
}
