<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class RestoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('restore', $this->route('deletedInvoice'));
    }

    public function rules(): array
    {
        return [];
    }
}
