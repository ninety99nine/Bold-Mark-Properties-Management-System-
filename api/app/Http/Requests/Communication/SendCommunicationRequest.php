<?php

namespace App\Http\Requests\Communication;

use App\Enums\CommunicationType;
use App\Enums\RecipientGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCommunicationRequest extends FormRequest
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
            'from_email'         => ['nullable', 'email', 'max:255'],
            'type'               => ['nullable', Rule::in(CommunicationType::values())],
            'recipient_groups'   => ['required', 'array', 'min:1'],
            'recipient_groups.*' => [Rule::in(RecipientGroup::values())],
            'subject'            => ['nullable', 'string', 'max:255'],
            'body'               => ['nullable', 'string'],
            'bcc'                => ['nullable', 'string', 'max:2000'],
            'attachments'        => ['nullable', 'array'],
            'attachments.*'      => ['file', 'max:7168'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'recipient_groups.required' => 'Please select at least one recipient group.',
            'recipient_groups.min'      => 'Please select at least one recipient group.',
            'attachments.*.max'         => 'Each attachment may not exceed 7 MB.',
        ];
    }
}
