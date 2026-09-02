<?php

namespace App\Http\Requests\Budget;

use App\Models\CommunityBudget;
use Illuminate\Foundation\Http\FormRequest;

class ImportBudgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [CommunityBudget::class, $this->route('community')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'fund' => ['sometimes', 'in:main,reserve'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
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
            'file.required' => 'A budget spreadsheet is required.',
            'file.mimes'    => 'The budget file must be an .xlsx, .xls or .csv file.',
        ];
    }
}
