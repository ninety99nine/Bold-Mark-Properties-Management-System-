<?php

namespace App\Http\Requests\Post;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'category'  => ['required', 'string', 'max:100'],
            'thumbnail' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:5120'],
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
            'title.required'     => 'The post title is required.',
            'title.string'       => 'The post title must be a string.',
            'title.max'          => 'The post title must not exceed 255 characters.',
            'body.required'      => 'The post body is required.',
            'body.string'        => 'The post body must be a string.',
            'category.required'  => 'The post category is required.',
            'category.string'    => 'The post category must be a string.',
            'category.max'       => 'The post category must not exceed 100 characters.',
            'thumbnail.file'     => 'The thumbnail must be a valid file.',
            'thumbnail.mimes'    => 'The thumbnail must be a JPEG, PNG, JPG, GIF, or SVG.',
            'thumbnail.max'      => 'The thumbnail size must not exceed 5MB.',
        ];
    }
}
