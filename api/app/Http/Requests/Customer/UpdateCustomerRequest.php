<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerType;
use App\Enums\PaymentType;
use App\Helpers\BankHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('updateCustomer', [$this->route('community'), $this->route('owner')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'customer_type'       => ['sometimes', Rule::in(CustomerType::values())],
            'full_name'           => ['sometimes', 'string', 'max:255'],
            'customer_code'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'vat_no'              => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'               => ['sometimes', 'nullable', 'email', 'max:255'],
            'secondary_emails'    => ['sometimes', 'nullable', 'array'],
            'secondary_emails.*'  => ['email', 'max:255'],
            'phone'               => ['sometimes', 'nullable', 'string', 'max:30'],
            'id_number'           => ['sometimes', 'nullable', 'string', 'max:50'],
            'customer_group_ids'  => ['sometimes', 'nullable', 'array'],
            'customer_group_ids.*' => [
                'uuid',
                Rule::exists('customer_groups', 'id')->where('community_id', $this->route('community')->id),
            ],
            'reference'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'old_customer_code'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'country'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'alt_email'           => ['sometimes', 'nullable', 'email', 'max:255'],
            'alt_phone'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'payment_type'        => ['sometimes', 'nullable', Rule::in(PaymentType::values())],
            'unit_id'             => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('units', 'id')->where('community_id', $this->route('community')->id),
            ],
            'pdf_password'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'address_line_2'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'suburb'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'town'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'account_holder'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_name'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_type'        => ['sometimes', 'nullable', Rule::in(BankHelper::accountTypes())],
            'account_number'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'branch_code'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'branch_name'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'               => ['sometimes', 'nullable', 'string', 'max:5000'],
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
            'customer_type.in'            => 'The selected customer type is invalid.',
            'email.email'                 => 'The email address must be a valid email.',
            'alt_email.email'             => 'The alternate email address must be a valid email.',
            'payment_type.in'             => 'The selected payment type is invalid.',
            'account_type.in'             => 'The selected account type is invalid.',
            'customer_group_ids.*.exists' => 'One or more selected customer groups are invalid.',
            'unit_id.exists'              => 'The selected unit is invalid.',
        ];
    }
}
