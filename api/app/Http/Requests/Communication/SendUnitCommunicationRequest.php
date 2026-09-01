<?php

namespace App\Http\Requests\Communication;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;

class SendUnitCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [Unit::class, $this->route('community'), $this->route('unit')]);
    }

    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email', 'max:255'],
            'recipient_name'  => ['nullable', 'string', 'max:255'],
            'bcc'             => ['nullable', 'string', 'max:1000'],
            'subject'         => ['required', 'string', 'max:255'],
            'body'            => ['nullable', 'string', 'max:100000'],
            'attachments'     => ['nullable', 'array', 'max:10'],
            'attachments.*'   => ['file', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_email.required' => 'A recipient e-mail address is required.',
            'subject.required'         => 'A subject is required.',
        ];
    }
}
