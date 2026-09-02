<?php

namespace App\Http\Requests\CreditNote;

use App\Models\CreditNote;
use Illuminate\Foundation\Http\FormRequest;

class ShowCreditableInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CreditNote::class);
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'uuid', 'exists:units,id'],
        ];
    }
}
