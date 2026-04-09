<?php

namespace App\Services;

use Exception;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CommentResource;
use App\Http\Resources\CommentResources;

class CommentService extends BaseService
{
    /**
     * Show comments.
     *
     * @param Post $post
     * @param array $data
     * @return CommentResources|array
     */
    public function showComments(Post $post, array $data): CommentResources|array
    {
        $query = Comment::where('post_id', $post->id);
        if (!request()->has('_sort')) $query = $query->latest();

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create comment.
     *
     * @param Post $post
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createComment(Post $post, array $data): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $parentId       = $data['parent_id'] ?? null;
        $templateId     = $data['template_id'] ?? null;
        $attachmentUrl  = $data['attachment_url'] ?? null;
        $commentData    = collect($data)->only(['body'])->toArray();

        $comment = Comment::create([
            ...$commentData,
            'status'     => 'published',
            'post_id'    => $post->id,
            'parent_id'  => $parentId,
            'created_by' => $user->id,
        ]);

        // Optionally seed content from a template
        if ($templateId) {
            $templateContent = (new CommentTemplateService())->getContentForCreation($templateId);
            if ($templateContent) {
                $comment->update(['body' => $templateContent]);
            }
        }

        return $this->showCreatedResource($comment);
    }

    /**
     * Delete comments.
     *
     * @param Post $post
     * @param array $commentIds
     * @return array
     * @throws Exception
     */
    public function deleteComments(Post $post, array $commentIds): array
    {
        $comments = Comment::whereIn('id', $commentIds)->get();

        if ($total = $comments->count()) {
            foreach ($comments as $comment) {
                $this->deleteComment($post, $comment);
            }
            return ['message' => $total . ($total === 1 ? ' Comment' : ' Comments') . ' deleted'];
        }

        throw new Exception('No Comments deleted');
    }

    /**
     * Show comment.
     *
     * @param Post $post
     * @param Comment $comment
     * @return CommentResource
     */
    public function showComment(Post $post, Comment $comment): CommentResource
    {
        return $this->showResource($comment);
    }

    /**
     * Update comment.
     *
     * @param Post $post
     * @param Comment $comment
     * @param array $data
     * @return array
     */
    public function updateComment(Post $post, Comment $comment, array $data): array
    {
        if (isset($data['publish']) && $data['publish'] === true) {
            $draftBody = $data['draft_body'] ?? $comment->draft_body;

            if ($draftBody) {
                $comment->body       = $draftBody;
                $comment->draft_body = $draftBody;
            }

            unset($data['publish']);
        }

        $comment->update($data);

        return $this->showUpdatedResource($comment);
    }

    /**
     * Delete comment.
     *
     * @param Post $post
     * @param Comment $comment
     * @return array
     */
    public function deleteComment(Post $post, Comment $comment): array
    {
        $deleted = $comment->forceDelete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Comment deleted' : 'Comment delete unsuccessful',
        ];
    }
}
