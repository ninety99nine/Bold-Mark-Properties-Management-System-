<?php

namespace App\Http\Requests\Journal;

use App\Models\JournalBatch;
use Illuminate\Foundation\Http\FormRequest;

class UploadJournalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', JournalBatch::class);
    }

    public function rules(): array
    {
        return [
            'community_id'   => ['required', 'uuid', 'exists:communities,id'],
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'date'           => ['required', 'date'],
            'journal_group'  => ['nullable', 'string', 'in:' . implode(',', JournalBatch::GROUPS)],
            'journal_file'   => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],

            'files'   => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'journal_file.required' => 'Please choose a completed journal batch file to upload.',
            'journal_file.mimes'    => 'The journal batch file must be an Excel (.xlsx / .xls) or CSV file.',
        ];
    }
}
