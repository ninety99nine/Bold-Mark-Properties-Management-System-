<?php

namespace App\Http\Requests\Community;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class ShowSupplierStatementsRequest extends FormRequest
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
            'date_from'     => ['nullable', 'date'],
            'date_to'       => ['nullable', 'date'],
            // Flags arrive as query-string strings ("true"/"1"); parsed with filter_var.
            'hide_zero'     => ['nullable'],
            'hide_negative' => ['nullable'],
            '_search'       => ['nullable', 'string'],
        ];
    }
}
