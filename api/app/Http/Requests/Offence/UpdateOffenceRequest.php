<?php

namespace App\Http\Requests\Offence;

use App\Enums\OffenceStatus;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOffenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'status'         => ['sometimes', Rule::in(OffenceStatus::values())],
            'issued_date'    => ['sometimes', 'nullable', 'date'],
            'description'    => ['sometimes', 'nullable', 'string', 'max:5000'],
            'rules'          => ['sometimes', 'nullable', 'array', 'max:20'],
            'rules.*.rule'   => ['nullable', 'string', 'max:500'],
            'rules.*.clause' => ['nullable', 'string', 'max:500'],
            'attachments'    => ['sometimes', 'nullable', 'array', 'max:10'],
            'attachments.*'  => ['file', 'max:10240'],
        ];
    }
}
