<?php

namespace App\Http\Requests\Comment;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCommentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('deleteAny', Comment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'comment_ids'   => ['required', 'array', 'min:1'],
            'comment_ids.*' => ['uuid'],
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
            'comment_ids.required' => 'The comment IDs are required.',
            'comment_ids.array'    => 'The comment IDs must be an array.',
            'comment_ids.min'      => 'At least one comment ID is required.',
            'comment_ids.*.uuid'   => 'Each comment ID must be a valid UUID.',
        ];
    }
}
