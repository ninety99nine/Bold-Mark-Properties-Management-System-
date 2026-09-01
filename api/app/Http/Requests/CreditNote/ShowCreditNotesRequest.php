<?php

namespace App\Http\Requests\CreditNote;

use App\Models\CreditNote;
use Illuminate\Foundation\Http\FormRequest;

class ShowCreditNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CreditNote::class);
    }

    public function rules(): array
    {
        return [
            'country'        => ['nullable', 'string', 'size:2'],
            'community_id'   => ['nullable', 'uuid', 'exists:communities,id'],
            'unit_id'        => ['nullable', 'uuid', 'exists:units,id'],
            'billed_to_type' => ['nullable', 'string', 'in:owner,occupant'],
            'billed_to_id'   => ['nullable', 'uuid'],
            'search'         => ['nullable', 'string', 'max:255'],
        ];
    }
}
