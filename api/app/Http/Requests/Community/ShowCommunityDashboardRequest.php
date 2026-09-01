<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class ShowCommunityDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('community'));
    }

    public function rules(): array
    {
        return [
            'financial_year' => ['nullable', 'string', 'max:20'],
        ];
    }
}
