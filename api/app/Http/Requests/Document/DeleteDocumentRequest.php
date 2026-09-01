<?php

namespace App\Http\Requests\Document;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class DeleteDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [];
    }
}
