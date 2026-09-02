<?php

namespace App\Http\Requests\Community;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class EmailSupplierStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Supplier::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ];
    }
}
