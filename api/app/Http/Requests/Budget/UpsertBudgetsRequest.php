<?php

namespace App\Http\Requests\Budget;

use App\Models\CommunityBudget;
use Illuminate\Foundation\Http\FormRequest;

class UpsertBudgetsRequest extends FormRequest
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
            'year'                => ['required', 'integer', 'min:2000', 'max:2100'],
            'fund'                => ['sometimes', 'in:main,reserve'],
            'rows'                => ['required', 'array', 'min:1'],
            'rows.*.ledger_id'    => ['required', 'string', 'exists:ledgers,id'],
            'rows.*.equal_monthly' => ['sometimes', 'boolean'],
            'rows.*.per_year'     => ['sometimes', 'numeric'],
            'rows.*.jan'          => ['sometimes', 'numeric'],
            'rows.*.feb'          => ['sometimes', 'numeric'],
            'rows.*.mar'          => ['sometimes', 'numeric'],
            'rows.*.apr'          => ['sometimes', 'numeric'],
            'rows.*.may'          => ['sometimes', 'numeric'],
            'rows.*.jun'          => ['sometimes', 'numeric'],
            'rows.*.jul'          => ['sometimes', 'numeric'],
            'rows.*.aug'          => ['sometimes', 'numeric'],
            'rows.*.sep'          => ['sometimes', 'numeric'],
            'rows.*.oct'          => ['sometimes', 'numeric'],
            'rows.*.nov'          => ['sometimes', 'numeric'],
            'rows.*.dec'          => ['sometimes', 'numeric'],
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
            'year.required' => 'The budget year is required.',
            'rows.required' => 'At least one budget row is required.',
        ];
    }
}
