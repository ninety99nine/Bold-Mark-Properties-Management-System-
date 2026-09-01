<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class DeleteLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('ledger'));
    }

    public function rules(): array
    {
        return [];
    }
}
