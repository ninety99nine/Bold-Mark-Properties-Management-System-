<?php

namespace App\Http\Requests\CreditNote;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('creditNote'));
    }

    public function rules(): array
    {
        return [];
    }
}
