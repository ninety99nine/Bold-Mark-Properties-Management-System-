<?php

namespace App\Http\Requests\AllocationRule;

use App\Models\AllocationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowAllocationRulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', AllocationRule::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
