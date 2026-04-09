<?php

namespace App\Http\Requests\Estate;

use Illuminate\Foundation\Http\FormRequest;

class ShowEstateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('estate'));
    }

    public function rules(): array
    {
        return [];
    }
}
