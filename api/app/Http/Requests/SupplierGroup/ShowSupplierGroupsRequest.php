<?php

namespace App\Http\Requests\SupplierGroup;

use App\Models\SupplierGroup;
use Illuminate\Foundation\Http\FormRequest;

class ShowSupplierGroupsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', SupplierGroup::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'community_id' => ['sometimes', 'nullable', 'uuid', 'exists:communities,id'],
            'search'       => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
