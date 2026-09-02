<?php

namespace App\Http\Requests\Occupant;

use App\Models\Occupant;
use Illuminate\Foundation\Http\FormRequest;

class ReinstateOccupantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reinstate', [Occupant::class, $this->route('unit'), $this->route('occupant')]);
    }

    public function rules(): array
    {
        return [];
    }
}
