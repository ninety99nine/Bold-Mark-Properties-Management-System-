<?php

namespace App\Http\Requests\CustomerGroup;

use App\Models\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;

class ShowCustomerGroupsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [CustomerGroup::class, $this->route('community')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }
}
