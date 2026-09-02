<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class ShowCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('community'));
    }

    public function rules(): array
    {
        return [];
    }
}
