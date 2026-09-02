<?php

namespace App\Http\Requests\SupplierInvoice;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierInvoiceType;
use App\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowSupplierInvoicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', SupplierInvoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'status'      => ['nullable', Rule::in(SupplierInvoiceStatus::values())],
            'type'        => ['nullable', Rule::in(SupplierInvoiceType::values())],
            'supplier_id' => ['nullable', 'uuid'],
            'month'       => ['nullable', 'string'], // "YYYY-MM"
            '_search'     => ['nullable', 'string'],
        ];
    }
}
