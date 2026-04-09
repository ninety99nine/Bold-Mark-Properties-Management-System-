<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_name'  => ['required', 'string', 'max:255'],
            'recipient_email' => ['required', 'email'],
            'subject'         => ['required', 'string', 'max:500'],
            'body'            => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_name.required'  => 'The recipient name is required.',
            'recipient_email.required' => 'The recipient email is required.',
            'recipient_email.email'    => 'A valid recipient email address is required.',
            'subject.required'         => 'The email subject is required.',
            'body.required'            => 'The email body is required.',
        ];
    }
}
