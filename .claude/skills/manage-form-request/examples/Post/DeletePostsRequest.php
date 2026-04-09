<?php

namespace App\Http\Requests\Post;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;

class DeletePostsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', Post::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'post_ids'   => ['required', 'array', 'min:1'],
            'post_ids.*' => ['uuid'],
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
            'post_ids.required' => 'The post IDs are required.',
            'post_ids.array'    => 'The post IDs must be an array.',
            'post_ids.min'      => 'At least one post ID is required.',
            'post_ids.*.uuid'   => 'Each post ID must be a valid UUID.',
        ];
    }
}
