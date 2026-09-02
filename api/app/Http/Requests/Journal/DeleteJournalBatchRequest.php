<?php

namespace App\Http\Requests\Journal;

use Illuminate\Foundation\Http\FormRequest;

class DeleteJournalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('journalBatch'));
    }

    public function rules(): array
    {
        return [];
    }
}
