<?php

namespace App\Http\Requests\Occupant;

use App\Models\Occupant;
use Illuminate\Foundation\Http\FormRequest;

class MoveOutOccupantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moveOut', [Occupant::class, $this->route('unit'), $this->route('occupant')]);
    }

    public function rules(): array
    {
        return [
            'move_out_date'   => ['nullable', 'date'],
            'move_out_reason' => ['nullable', 'string', 'max:255'],
            'move_out_notes'  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
