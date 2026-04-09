<?php

namespace App\Http\Requests\CashbookEntry;

use App\Models\CashbookEntry;
use Illuminate\Foundation\Http\FormRequest;

class AutoAllocateCashbookEntriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('autoAllocate', CashbookEntry::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estate_id' => ['required', 'uuid', 'exists:estates,id'],
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
            'estate_id.required' => 'The estate is required for auto-allocation.',
            'estate_id.uuid'     => 'The estate ID must be a valid UUID.',
            'estate_id.exists'   => 'The selected estate does not exist.',
        ];
    }
}
