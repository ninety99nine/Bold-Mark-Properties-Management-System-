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
            'community_id' => ['required', 'uuid', 'exists:communities,id'],
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
            'community_id.required' => 'The community is required for auto-allocation.',
            'community_id.uuid'     => 'The community ID must be a valid UUID.',
            'community_id.exists'   => 'The selected community does not exist.',
        ];
    }
}
