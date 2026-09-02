<?php

namespace App\Http\Requests\Budget;

use App\Models\CommunityBudget;
use Illuminate\Foundation\Http\FormRequest;

class TemplateBudgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [CommunityBudget::class, $this->route('community')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'fund' => ['sometimes', 'in:main,reserve'],
        ];
    }
}
