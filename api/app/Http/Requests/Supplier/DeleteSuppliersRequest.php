<?php

namespace App\Http\Requests\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class DeleteSuppliersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', Supplier::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'supplier_ids'   => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['uuid'],
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
            'supplier_ids.required' => 'The supplier IDs are required.',
            'supplier_ids.array'    => 'The supplier IDs must be an array.',
            'supplier_ids.min'      => 'At least one supplier ID is required.',
            'supplier_ids.*.uuid'   => 'Each supplier ID must be a valid UUID.',
        ];
    }
}
