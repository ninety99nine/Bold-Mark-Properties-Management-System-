<?php

namespace App\Http\Requests\ChargeType;

use Illuminate\Foundation\Http\FormRequest;

class DeleteChargeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('chargeType'));
    }

    public function rules(): array
    {
        return [];
    }
}
