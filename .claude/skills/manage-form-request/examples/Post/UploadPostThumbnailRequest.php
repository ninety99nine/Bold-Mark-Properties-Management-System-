<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class UploadPostThumbnailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'thumbnail' => ['required', 'file', 'image', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'thumbnail.required' => 'Please select an image file.',
            'thumbnail.image'    => 'The file must be an image.',
            'thumbnail.max'      => 'The image may not be larger than 2MB.',
        ];
    }
}
