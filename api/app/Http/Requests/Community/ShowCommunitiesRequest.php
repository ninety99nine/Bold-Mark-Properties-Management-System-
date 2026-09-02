<?php

namespace App\Http\Requests\Community;

use App\Enums\CommunityEntityType;
use App\Enums\CommunityStatus;
use App\Models\Community;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowCommunitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Community::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['nullable', Rule::in(CommunityEntityType::values())],
            'status'      => ['nullable', Rule::in(CommunityStatus::values())],
            'is_active'   => ['nullable', 'boolean'],
            'country'     => ['nullable', 'string', 'max:3'],
        ];
    }
}
