<?php

namespace App\Http\Requests\CashbookEntry;

use App\Enums\JournalLineType;
use App\Enums\VatType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SplitCashbookEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('allocate', $this->route('cashbookEntry'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'lines'               => ['required', 'array', 'min:1'],
            'lines.*.ledger_type' => ['required', Rule::in(JournalLineType::values())],
            'lines.*.target'      => ['required', 'string'],
            'lines.*.amount'      => ['required', 'numeric'],
            'lines.*.vat_type'    => ['nullable', Rule::in(VatType::values())],
            'lines.*.remarks'     => ['nullable', 'string', 'max:500'],
            'save_template'       => ['nullable', 'boolean'],
            'template_name'       => ['nullable', 'required_if:save_template,true', 'string', 'max:255'],
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
            'lines.required'               => 'At least one split line is required.',
            'lines.array'                  => 'The split lines must be an array.',
            'lines.min'                    => 'At least one split line is required.',
            'lines.*.ledger_type.required' => 'Each split line requires a ledger type.',
            'lines.*.ledger_type.in'       => 'A split line has an invalid ledger type.',
            'lines.*.target.required'      => 'Each split line requires an account.',
            'lines.*.amount.required'      => 'Each split line requires an amount.',
            'lines.*.amount.numeric'       => 'Each split line amount must be a number.',
            'lines.*.vat_type.in'          => 'A split line has an invalid VAT type.',
            'lines.*.remarks.max'          => 'A split line remark may not exceed 500 characters.',
            'template_name.required_if'    => 'A template name is required when saving a template.',
            'template_name.max'            => 'The template name may not exceed 255 characters.',
        ];
    }
}
