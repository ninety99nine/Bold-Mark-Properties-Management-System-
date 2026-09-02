<?php

namespace App\Http\Requests\CommunityUser;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDirectorsTrusteesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('community'));
    }

    public function rules(): array
    {
        return [
            'member_ids'   => ['present', 'array'],
            'member_ids.*' => ['string', 'exists:community_members,id'],
        ];
    }
}
