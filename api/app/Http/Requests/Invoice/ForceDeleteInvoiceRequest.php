<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class ForceDeleteInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('forceDelete', $this->route('deletedInvoice'));
    }

    public function rules(): array
    {
        return [];
    }
}
