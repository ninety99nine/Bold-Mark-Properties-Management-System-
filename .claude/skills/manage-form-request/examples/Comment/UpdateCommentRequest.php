<?php

namespace App\Http\Requests\Comment;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', [Comment::class, $this->route('post'), $this->route('comment')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'body'           => ['sometimes', 'string', 'max:2000'],
            'attachment_url' => ['nullable', 'url', 'max:500'],
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
            'body.string' => 'The comment body must be a string.',
            'body.max'    => 'The comment body must not exceed 2000 characters.',
        ];
    }
}
