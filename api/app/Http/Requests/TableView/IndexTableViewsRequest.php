<?php

namespace App\Http\Requests\TableView;

use Illuminate\Foundation\Http\FormRequest;

class IndexTableViewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'context' => ['required', 'string', 'in:units,invoices,cashbook,age-analysis,users'],
        ];
    }
}
