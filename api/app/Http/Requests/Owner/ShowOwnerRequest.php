<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class ShowOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('owner'));
    }

    public function rules(): array
    {
        return [];
    }
}
