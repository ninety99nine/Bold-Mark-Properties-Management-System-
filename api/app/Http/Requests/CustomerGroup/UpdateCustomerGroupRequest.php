<?php

namespace App\Http\Requests\CustomerGroup;

use App\Models\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', [CustomerGroup::class, $this->route('community'), $this->route('customerGroup')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('customer_groups', 'name')
                    ->where('community_id', $this->route('community')->id)
                    ->ignore($this->route('customerGroup')->id),
            ],
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
            'name.unique' => 'A customer group with this name already exists.',
        ];
    }
}
