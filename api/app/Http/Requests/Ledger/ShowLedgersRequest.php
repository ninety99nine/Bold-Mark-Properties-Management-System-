<?php

namespace App\Http\Requests\Ledger;

use App\Models\Ledger;
use Illuminate\Foundation\Http\FormRequest;

class ShowLedgersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Ledger::class);
    }

    public function rules(): array
    {
        return [
            'fund'       => ['sometimes', 'in:main,reserve'],
            'grouped'    => ['sometimes', 'boolean'],
            'applies_to' => ['sometimes', 'string'],
            // next-code preview params
            'type'       => ['sometimes', 'in:main,sub'],
            'prefix'     => ['sometimes', 'string'],
            'parent_id'  => ['sometimes', 'uuid'],
        ];
    }
}
