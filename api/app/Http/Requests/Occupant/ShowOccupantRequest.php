<?php

namespace App\Http\Requests\Occupant;

use App\Models\Occupant;
use Illuminate\Foundation\Http\FormRequest;

class ShowOccupantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', [Occupant::class, $this->route('unit'), $this->route('occupant')]);
    }

    public function rules(): array
    {
        return [];
    }
}
