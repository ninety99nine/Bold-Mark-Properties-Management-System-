<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class ShowDeletedInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Invoice::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
