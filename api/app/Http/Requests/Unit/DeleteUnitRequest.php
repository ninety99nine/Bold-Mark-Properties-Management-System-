<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', [Unit::class, $this->route('estate'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [];
    }
}
