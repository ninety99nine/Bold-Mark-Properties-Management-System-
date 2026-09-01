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
        return [];
    }
}
