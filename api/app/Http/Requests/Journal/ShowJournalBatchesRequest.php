<?php

namespace App\Http\Requests\Journal;

use App\Models\JournalBatch;
use Illuminate\Foundation\Http\FormRequest;

class ShowJournalBatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', JournalBatch::class);
    }

    public function rules(): array
    {
        return [
            'community_id'   => ['required', 'uuid', 'exists:communities,id'],
            'financial_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'date_from'      => ['nullable', 'date'],
            'date_to'        => ['nullable', 'date'],
            'search'         => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'community_id.required' => 'A community is required.',
            'community_id.exists'   => 'The selected community does not exist.',
        ];
    }
}
