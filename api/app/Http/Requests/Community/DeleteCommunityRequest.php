<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('community'));
    }

    public function rules(): array
    {
        return [];
    }
}
