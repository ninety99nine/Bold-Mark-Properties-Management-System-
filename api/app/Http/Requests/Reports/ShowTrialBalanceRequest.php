<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ShowTrialBalanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewCustomers', $this->route('community'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'financial_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'as_at'          => ['nullable', 'date'],
            'fund'           => ['nullable', 'in:main,reserve'],
            'hide_zero'      => ['nullable'],
            'export'         => ['nullable'],
        ];
    }
}
