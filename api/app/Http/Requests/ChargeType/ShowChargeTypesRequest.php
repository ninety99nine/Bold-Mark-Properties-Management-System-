<?php

namespace App\Http\Requests\ChargeType;

use App\Models\ChargeType;
use Illuminate\Foundation\Http\FormRequest;

class ShowChargeTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ChargeType::class);
    }

    public function rules(): array
    {
        return [];
    }
}
