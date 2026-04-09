<?php

namespace App\Http\Requests\Owner;

use App\Models\Owner;
use Illuminate\Foundation\Http\FormRequest;

class DeleteOwnersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', Owner::class);
    }

    public function rules(): array
    {
        return [
            'owner_ids'   => ['required', 'array', 'min:1'],
            'owner_ids.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_ids.required' => 'At least one owner ID is required.',
            'owner_ids.min'      => 'At least one owner ID must be provided.',
            'owner_ids.*.uuid'   => 'Each owner ID must be a valid UUID.',
        ];
    }
}
