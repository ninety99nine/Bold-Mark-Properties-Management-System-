<?php

namespace App\Http\Requests\Supplier;

use App\Enums\SupplierAccountType;
use App\Enums\SupplierPaymentType;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('supplier'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name'                => ['sometimes', 'string', 'max:255'],
            'supplier_code'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'reference'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'supplier_type'       => ['sometimes', 'nullable', Rule::in(SupplierType::values())],
            'status'              => ['sometimes', 'nullable', Rule::in(SupplierStatus::values())],
            'payment_type'        => ['sometimes', 'nullable', Rule::in(SupplierPaymentType::values())],
            'email'               => ['sometimes', 'nullable', 'email', 'max:255'],
            'alt_email'           => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone'               => ['sometimes', 'nullable', 'string', 'max:30'],
            'alt_phone'           => ['sometimes', 'nullable', 'string', 'max:20'],
            'bank_name'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_type'        => ['sometimes', 'nullable', Rule::in(SupplierAccountType::values())],
            'account_number'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'branch_code'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'branch_name'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'vat_number'          => ['sometimes', 'nullable', 'string', 'max:50'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'             => ['sometimes', 'nullable', 'string', 'max:500'],
            'address_line_1'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'suburb'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'town'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active'           => ['sometimes', 'boolean'],
            'balance'             => ['sometimes', 'nullable', 'numeric'],
            'supplier_group_id'   => ['sometimes', 'nullable', 'uuid', 'exists:supplier_groups,id'],
            'community_id'        => ['sometimes', 'nullable', 'uuid', 'exists:communities,id'],
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
            'name.string'            => 'The supplier name must be a string.',
            'name.max'               => 'The supplier name must not exceed 255 characters.',
            'email.email'            => 'The email address must be a valid email.',
            'alt_email.email'        => 'The alternate email address must be a valid email.',
            'balance.numeric'        => 'The balance must be a number.',
            'supplier_group_id.uuid'   => 'The supplier group ID must be a valid UUID.',
            'supplier_group_id.exists' => 'The selected supplier group does not exist.',
            'community_id.uuid'      => 'The community ID must be a valid UUID.',
            'community_id.exists'    => 'The selected community does not exist.',
        ];
    }
}
