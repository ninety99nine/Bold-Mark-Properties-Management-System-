<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Ownership and permission are validated in the controller/service layer.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name'    => ['sometimes', 'string', 'max:255'],
            'company_slogan'  => ['nullable', 'string', 'max:255'],
            'contact_email'   => ['sometimes', 'email', 'max:255'],
            'contact_phone'   => ['nullable', 'string', 'max:30'],
            'address'         => ['nullable', 'string', 'max:500'],
            'country'         => ['sometimes', 'string', 'size:2'],
            'currency'        => ['sometimes', 'string', 'max:10'],
            'primary_color'   => ['nullable', 'string', 'max:10', 'regex:/^#[0-9A-Fa-f]{3,6}$/'],
            'secondary_color' => ['nullable', 'string', 'max:10', 'regex:/^#[0-9A-Fa-f]{3,6}$/'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.string'       => 'The company name must be a string.',
            'company_name.max'          => 'The company name may not exceed 255 characters.',
            'company_slogan.string'     => 'The company slogan must be a string.',
            'company_slogan.max'        => 'The company slogan may not exceed 255 characters.',
            'contact_email.email'       => 'The contact email must be a valid email address.',
            'contact_email.max'         => 'The contact email may not exceed 255 characters.',
            'contact_phone.max'         => 'The contact phone may not exceed 30 characters.',
            'address.max'               => 'The address may not exceed 500 characters.',
            'country.size'              => 'The country code must be exactly 2 characters.',
            'currency.max'              => 'The currency code may not exceed 10 characters.',
            'primary_color.max'         => 'The primary colour value may not exceed 10 characters.',
            'primary_color.regex'       => 'The primary colour must be a valid hex colour code (e.g. #1F3A5C or #FFF).',
            'secondary_color.max'       => 'The secondary colour value may not exceed 10 characters.',
            'secondary_color.regex'     => 'The secondary colour must be a valid hex colour code (e.g. #D89B4B or #FFF).',
        ];
    }
}
