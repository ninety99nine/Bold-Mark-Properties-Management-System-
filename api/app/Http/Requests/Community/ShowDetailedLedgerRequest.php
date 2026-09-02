<?php

namespace App\Http\Requests\Community;

use App\Enums\CollectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowDetailedLedgerRequest extends FormRequest
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
            'date_from'       => ['nullable', 'date'],
            'date_to'         => ['nullable', 'date'],
            'customer_ids'    => ['nullable', 'array'],
            'customer_ids.*'  => ['uuid'],
            'group_ids'       => ['nullable', 'array'],
            'group_ids.*'     => ['uuid'],
            'statuses'        => ['nullable', 'array'],
            'statuses.*'      => [Rule::in(CollectionStatus::values())],
            'all_customers'   => ['nullable'],
            'hide_zero'       => ['nullable'],
            'show_line_items' => ['nullable'],
        ];
    }
}
