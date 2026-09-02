<?php

namespace App\Http\Requests\Supplier;

use App\Enums\SupplierAccountType;
use App\Enums\SupplierPaymentType;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Supplier::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'supplier_code'       => ['nullable', 'string', 'max:50'],
            'reference'           => ['nullable', 'string', 'max:255'],
            'supplier_type'       => ['nullable', Rule::in(SupplierType::values())],
            'status'              => ['nullable', Rule::in(SupplierStatus::values())],
            'payment_type'        => ['nullable', Rule::in(SupplierPaymentType::values())],
            'email'               => ['required', 'email', 'max:255'],
            'alt_email'           => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'alt_phone'           => ['nullable', 'string', 'max:20'],
            'bank_name'           => ['nullable', 'string', 'max:255'],
            'account_type'        => ['nullable', Rule::in(SupplierAccountType::values())],
            'account_number'      => ['nullable', 'string', 'max:50'],
            'branch_code'         => ['nullable', 'string', 'max:50'],
            'branch_name'         => ['nullable', 'string', 'max:255'],
            'vat_number'          => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'address'             => ['nullable', 'string', 'max:500'],
            'address_line_1'      => ['nullable', 'string', 'max:255'],
            'address_line_2'      => ['nullable', 'string', 'max:255'],
            'suburb'              => ['nullable', 'string', 'max:255'],
            'town'                => ['nullable', 'string', 'max:255'],
            'postal_code'         => ['nullable', 'string', 'max:20'],
            'is_active'           => ['nullable', 'boolean'],
            'balance'             => ['nullable', 'numeric'],
            'supplier_group_id'   => ['nullable', 'uuid', 'exists:supplier_groups,id'],
            'community_id'        => ['nullable', 'uuid', 'exists:communities,id'],
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
            'name.required'          => 'The supplier name is required.',
            'name.string'            => 'The supplier name must be a string.',
            'name.max'               => 'The supplier name must not exceed 255 characters.',
            'email.required'         => 'The email address is required.',
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
