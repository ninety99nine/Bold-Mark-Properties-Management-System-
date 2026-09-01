<?php

namespace App\Http\Requests\Community;

use App\Models\Community;
use Illuminate\Foundation\Http\FormRequest;

class ShowCommunityBillingSetupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('community') ?? Community::class);
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
