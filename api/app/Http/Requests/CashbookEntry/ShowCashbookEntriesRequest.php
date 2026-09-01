<?php

namespace App\Http\Requests\CashbookEntry;

use App\Models\CashbookEntry;
use Illuminate\Foundation\Http\FormRequest;

class ShowCashbookEntriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CashbookEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'community_id'        => ['sometimes', 'uuid', 'exists:communities,id'],
            'unit_id'          => ['sometimes', 'uuid', 'exists:units,id'],
            'type'             => ['sometimes', 'string', 'in:credit,debit'],
            'allocation_status'=> ['sometimes', 'string', 'in:allocated,unallocated'],
            'ledger_id'   => ['sometimes', 'uuid', 'exists:ledgers,id'],
            'date_range'       => ['sometimes', 'string'],
            'date_range_start' => ['sometimes', 'date'],
            'date_range_end'   => ['sometimes', 'date'],
            'country'          => ['nullable', 'string', 'max:3'],
        ];
    }
}
