<?php

namespace App\Http\Requests\Community;

use App\Enums\CollectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunNoticesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('applyCustomerStatuses', $this->route('community'));
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
            'debit_order'          => ['nullable', 'boolean'],
            'hide_zero'            => ['nullable', 'boolean'],
            'hide_negative'        => ['nullable', 'boolean'],
            'exclude_debit_arrear' => ['nullable', 'boolean'],
            '_search'              => ['nullable', 'string'],
            'unit_ids'             => ['nullable', 'array'],
            'unit_ids.*'           => ['uuid'],
            'send_email'           => ['nullable', 'boolean'],
            'email_unit_ids'       => ['nullable', 'array'],
            'email_unit_ids.*'     => ['uuid'],
        ];
    }
}
