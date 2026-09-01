<?php

namespace App\Http\Requests\CustomerStatus;

use App\Enums\CollectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyCustomerStatusesRequest extends FormRequest
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
        // The apply dropdown sends a domain-namespaced value (collection:* /
        // interest:*); bare collection values are still accepted (legacy).
        $allowed = array_merge(
            CollectionStatus::values(),
            array_map(fn ($s) => 'collection:' . $s, CollectionStatus::values()),
            array_map(fn ($s) => 'debt:' . $s, CollectionStatus::values()),
            ['interest:none', 'interest:exempt'],
        );

        return [
            'unit_ids'        => ['required', 'array', 'min:1'],
            'unit_ids.*'      => ['uuid'],
            'status'          => ['nullable', Rule::in($allowed)],
            'note'            => ['nullable', 'string', 'max:2000'],
            'status_date'     => ['nullable', 'date'],
            // Contextual controls (Letter of Demand / Handed Over).
            'send_email'      => ['nullable', 'boolean'],
            'send_sms'        => ['nullable', 'boolean'],
            'apply_charge'    => ['nullable', 'boolean'],
            'notify_attorney' => ['nullable', 'boolean'],
            'attorney'        => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'unit_ids.required' => 'Please select at least one customer.',
            'unit_ids.min'      => 'Please select at least one customer.',
            'status.in'         => 'The selected status is invalid.',
        ];
    }
}
