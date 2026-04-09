<?php

namespace App\Http\Requests\Owner;

use App\Models\Owner;
use Illuminate\Foundation\Http\FormRequest;

class ShowOwnersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Owner::class);
    }

    public function rules(): array
    {
        return [];
    }
}
