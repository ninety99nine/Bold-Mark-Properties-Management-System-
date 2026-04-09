<?php

namespace App\Http\Requests\ChargeType;

use Illuminate\Foundation\Http\FormRequest;

class ShowChargeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('chargeType'));
    }

    public function rules(): array
    {
        return [];
    }
}
