<?php

namespace App\Http\Requests\Comment;

use App\Models\Comment;
use App\Services\CommentTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Comment::class, $this->route('post')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $post = $this->route('post');
        $templateIds = array_column((new CommentTemplateService())->list(), 'id');

        return [
            'body'           => ['required', 'string', 'max:2000'],
            'parent_id'      => [
                'nullable',
                'uuid',
                Rule::exists('comments', 'id')->where('post_id', $post->id),
            ],
            'template_id'    => ['nullable', 'string', Rule::in($templateIds)],
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
            'body.required'      => 'The comment body is required.',
            'body.string'        => 'The comment body must be a string.',
            'body.max'           => 'The comment body must not exceed 2000 characters.',
            'parent_id.uuid'     => 'The parent comment ID must be a valid UUID.',
            'parent_id.exists'   => 'The selected parent comment does not exist on this post.',
        ];
    }
}
