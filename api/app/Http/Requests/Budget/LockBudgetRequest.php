<?php

namespace App\Http\Requests\Budget;

use App\Models\CommunityBudget;
use Illuminate\Foundation\Http\FormRequest;

class LockBudgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', [CommunityBudget::class, $this->route('community')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'fund' => ['sometimes', 'in:main,reserve'],
        ];
    }
}
