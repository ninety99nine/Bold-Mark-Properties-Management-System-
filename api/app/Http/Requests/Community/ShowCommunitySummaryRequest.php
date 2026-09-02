<?php

namespace App\Http\Requests\Community;

use App\Models\Community;
use Illuminate\Foundation\Http\FormRequest;

class ShowCommunitySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Community::class);
    }

    public function rules(): array
    {
        return [
            'country' => ['nullable', 'string', 'max:3'],
        ];
    }
}
