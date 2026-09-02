<?php

namespace App\Http\Requests\CashbookEntry;

use Illuminate\Foundation\Http\FormRequest;

class DeallocateEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('deallocate', $this->route('cashbookEntry'));
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
