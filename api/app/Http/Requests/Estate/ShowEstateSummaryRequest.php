<?php

namespace App\Http\Requests\Estate;

use App\Models\Estate;
use Illuminate\Foundation\Http\FormRequest;

class ShowEstateSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Estate::class);
    }

    public function rules(): array
    {
        return [];
    }
}
