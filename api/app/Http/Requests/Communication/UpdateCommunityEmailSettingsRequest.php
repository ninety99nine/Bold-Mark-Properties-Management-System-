<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityEmailSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('sendCommunication', $this->route('community'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'email_logo'   => ['nullable', 'image', 'max:5120'],
            'email_header' => ['nullable', 'image', 'max:5120'],
            'email_footer' => ['nullable', 'image', 'max:5120'],

            'remove_email_logo'   => ['nullable', 'boolean'],
            'remove_email_header' => ['nullable', 'boolean'],
            'remove_email_footer' => ['nullable', 'boolean'],
        ];
    }
}
