<?php

namespace App\Http\Requests\Ledger;

use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;

class DeleteLedgersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', Ledger::class);
    }

    public function rules(): array
    {
        return [
            'ledger_ids'   => ['required', 'array', 'min:1'],
            'ledger_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'ledger_ids.required' => 'At least one ledger ID is required.',
            'ledger_ids.min'      => 'At least one ledger ID must be provided.',
            'ledger_ids.*.uuid'   => 'Each ledger ID must be a valid UUID.',
        ];
    }
}
