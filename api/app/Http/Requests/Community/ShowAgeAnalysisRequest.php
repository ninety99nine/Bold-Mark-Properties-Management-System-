<?php

namespace App\Http\Requests\Community;

use App\Enums\CollectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowAgeAnalysisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewCustomers', $this->route('community'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'ageing_date'          => ['nullable', 'date'],
            'ledger_id'            => ['nullable', 'uuid'],
            'customer_group_id'    => ['nullable', 'uuid'],
            'filter_type'          => ['nullable', 'string', Rule::in(['all', 'no_status', 'handed_over', 'payment_arrangement'])],
            'debt_status'          => ['nullable', 'string', Rule::in(array_merge(['all'], CollectionStatus::values()))],
            // Flags arrive as query-string values ("true"/"1"); the service
            // parses them with filter_var, so accept any truthy representation.
            'debit_order'          => ['nullable'],
            'hide_zero'            => ['nullable'],
            'hide_negative'        => ['nullable'],
            'exclude_debit_arrear' => ['nullable'],
            '_search'              => ['nullable', 'string'],
        ];
    }
}
