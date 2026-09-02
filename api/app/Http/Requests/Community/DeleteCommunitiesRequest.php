<?php

namespace App\Http\Requests\Community;

use App\Models\Community;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCommunitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', Community::class);
    }

    public function rules(): array
    {
        return [
            'community_ids'   => ['required', 'array', 'min:1'],
            'community_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'community_ids.required' => 'At least one community ID is required.',
            'community_ids.min'      => 'At least one community ID must be provided.',
            'community_ids.*.uuid'   => 'Each community ID must be a valid UUID.',
        ];
    }
}
