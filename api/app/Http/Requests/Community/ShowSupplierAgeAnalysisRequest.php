<?php

namespace App\Http\Requests\Community;

use App\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

class ShowSupplierAgeAnalysisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', SupplierInvoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'ageing_date'   => ['nullable', 'date'],
            // Flags arrive as query-string values; the service parses them with
            // filter_var, so accept any truthy representation.
            'hide_zero'     => ['nullable'],
            'hide_negative' => ['nullable'],
            '_search'       => ['nullable', 'string'],
        ];
    }
}
