<?php

namespace App\Http\Requests\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class ExportSuppliersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Supplier::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'community_id'      => ['sometimes', 'nullable', 'uuid', 'exists:communities,id'],
            'supplier_group_id' => ['sometimes', 'nullable', 'uuid', 'exists:supplier_groups,id'],
            'status'            => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active'         => ['sometimes', 'boolean'],
            'search'            => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
