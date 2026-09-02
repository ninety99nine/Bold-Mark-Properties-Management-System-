<?php

namespace App\Http\Requests\SupplierGroup;

use App\Models\SupplierGroup;
use Illuminate\Foundation\Http\FormRequest;

class CreateSupplierGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', SupplierGroup::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'community_id' => ['nullable', 'uuid', 'exists:communities,id'],
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
            'name.required'       => 'The supplier group name is required.',
            'name.string'         => 'The supplier group name must be a string.',
            'name.max'            => 'The supplier group name must not exceed 255 characters.',
            'community_id.uuid'   => 'The community ID must be a valid UUID.',
            'community_id.exists' => 'The selected community does not exist.',
        ];
    }
}
