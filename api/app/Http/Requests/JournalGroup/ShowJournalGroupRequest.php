<?php

namespace App\Http\Requests\JournalGroup;

use App\Models\JournalGroup;
use Illuminate\Foundation\Http\FormRequest;

class ShowJournalGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('view', [JournalGroup::class, $this->route('community'), $this->route('journalGroup')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }
}
