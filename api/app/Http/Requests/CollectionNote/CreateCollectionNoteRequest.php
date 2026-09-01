<?php

namespace App\Http\Requests\CollectionNote;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class CreateCollectionNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:5000'],
        ];
    }
}
