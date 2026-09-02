<?php

namespace App\Http\Requests\Journal;

use Illuminate\Foundation\Http\FormRequest;

class ShowJournalBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('journalBatch'));
    }

    public function rules(): array
    {
        return [];
    }
}
