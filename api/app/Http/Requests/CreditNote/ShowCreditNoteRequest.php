<?php

namespace App\Http\Requests\CreditNote;

use Illuminate\Foundation\Http\FormRequest;

class ShowCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('creditNote'));
    }

    public function rules(): array
    {
        return [];
    }
}
