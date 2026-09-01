<?php

namespace App\Http\Requests\Offence;

use App\Enums\OffenceStatus;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOffenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'status'         => ['nullable', Rule::in(OffenceStatus::values())],
            'issued_date'    => ['nullable', 'date'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'rules'          => ['nullable', 'array', 'max:20'],
            'rules.*.rule'   => ['nullable', 'string', 'max:500'],
            'rules.*.clause' => ['nullable', 'string', 'max:500'],
            'attachments'    => ['nullable', 'array', 'max:10'],
            'attachments.*'  => ['file', 'max:10240'],
        ];
    }
}
