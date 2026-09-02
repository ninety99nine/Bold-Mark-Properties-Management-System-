<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ResetUserTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resetTwoFactor', $this->route('user'));
    }

    public function rules(): array
    {
        return [];
    }
}
