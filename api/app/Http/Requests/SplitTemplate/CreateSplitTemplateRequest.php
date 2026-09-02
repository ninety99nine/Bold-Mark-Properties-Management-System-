<?php

namespace App\Http\Requests\SplitTemplate;

use App\Enums\JournalLineType;
use App\Models\SplitTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSplitTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', SplitTemplate::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'lines'                 => ['required', 'array', 'min:1'],
            'lines.*.ledger_type'   => ['required', 'string', Rule::in(JournalLineType::values())],
            'lines.*.ledger_id'     => ['nullable', 'uuid', 'exists:ledgers,id'],
            'lines.*.unit_id'       => ['nullable', 'uuid', 'exists:units,id'],
            'lines.*.supplier_id'   => ['nullable', 'uuid', 'exists:suppliers,id'],
            'lines.*.remarks'       => ['nullable', 'string', 'max:500'],
            'lines.*.amount'        => ['required', 'numeric'],
            'lines.*.ratio'         => ['nullable', 'numeric'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'  => 'A template name is required.',
            'lines.required' => 'At least one split line is required.',
            'lines.min'      => 'At least one split line is required.',
        ];
    }
}
