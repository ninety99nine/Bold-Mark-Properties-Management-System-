<?php

namespace App\Http\Requests\Community;

use App\Models\Community;
use App\Models\CommunityBillingSetup;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityBillingSetupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('community') ?? Community::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (CommunityBillingSetup::LEDGER_FIELDS as $field) {
            $rules[$field] = ['nullable', 'uuid', 'exists:ledgers,id'];
        }

        foreach (CommunityBillingSetup::BOOLEAN_FIELDS as $field) {
            $rules[$field] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
