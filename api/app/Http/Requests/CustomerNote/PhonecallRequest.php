<?php

namespace App\Http\Requests\CustomerNote;

use Illuminate\Foundation\Http\FormRequest;

class PhonecallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('applyCustomerStatuses', $this->route('community'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'note'       => ['nullable', 'string', 'max:5000'],
            'add_charge' => ['nullable', 'boolean'],
        ];
    }
}
