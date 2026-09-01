<?php

namespace App\Http\Requests\Ledger;

use App\Enums\LedgerAppliesTo;
use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Ledger::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'applies_to'  => ['required', Rule::in(LedgerAppliesTo::values())],
            'is_recurring' => ['required', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'The ledger name is required.',
            'applies_to.required' => 'The applies_to field is required.',
            'applies_to.in'       => 'The applies_to must be one of: ' . implode(', ', LedgerAppliesTo::values()) . '.',
            'is_recurring.required' => 'The is_recurring field is required.',
        ];
    }
}
