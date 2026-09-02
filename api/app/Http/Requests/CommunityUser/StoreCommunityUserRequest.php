<?php

namespace App\Http\Requests\CommunityUser;

use App\Enums\CommunityMemberType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('community'));
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['nullable', 'email', 'max:255'],
            'cellphone'           => ['nullable', 'string', 'max:30'],
            'user_type'           => ['required', Rule::in(CommunityMemberType::values())],
            'is_director_trustee' => ['nullable', 'boolean'],
        ];
    }
}
