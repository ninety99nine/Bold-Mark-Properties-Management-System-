<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Comment;
use App\Services\CommentService;
use App\Http\Resources\CommentResource;
use App\Http\Resources\CommentResources;
use App\Http\Requests\Comment\ShowCommentRequest;
use App\Http\Requests\Comment\ShowCommentsRequest;
use App\Http\Requests\Comment\CreateCommentRequest;
use App\Http\Requests\Comment\DeleteCommentRequest;
use App\Http\Requests\Comment\DeleteCommentsRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;

class CommentController extends Controller
{
    /**
     * @var CommentService
     */
    protected $service;

    /**
     * CommentController constructor.
     *
     * @param CommentService $service
     */
    public function __construct(CommentService $service)
    {
        $this->service = $service;
    }

    /**
     * Show comments.
     *
     * @param ShowCommentsRequest $request
     * @param Post $post
     * @return CommentResources|array
     */
    public function showComments(ShowCommentsRequest $request, Post $post): CommentResources|array
    {
        return $this->service->showComments($post, $request->validated());
    }

    /**
     * Create comment.
     *
     * @param CreateCommentRequest $request
     * @param Post $post
     * @return array
     */
    public function createComment(CreateCommentRequest $request, Post $post): array
    {
        return $this->service->createComment($post, $request->validated());
    }

    /**
     * Delete comments.
     *
     * @param DeleteCommentsRequest $request
     * @param Post $post
     * @return array
     */
    public function deleteComments(DeleteCommentsRequest $request, Post $post): array
    {
        $commentIds = $request->input('comment_ids', []);
        return $this->service->deleteComments($post, $commentIds);
    }

    /**
     * Show comment.
     *
     * @param ShowCommentRequest $request
     * @param Post $post
     * @param Comment $comment
     * @return CommentResource
     */
    public function showComment(ShowCommentRequest $request, Post $post, Comment $comment): CommentResource
    {
        return $this->service->showComment($post, $comment);
    }

    /**
     * Update comment.
     *
     * @param UpdateCommentRequest $request
     * @param Post $post
     * @param Comment $comment
     * @return array
     */
    public function updateComment(UpdateCommentRequest $request, Post $post, Comment $comment): array
    {
        return $this->service->updateComment($post, $comment, $request->validated());
    }

    /**
     * Delete comment.
     *
     * @param DeleteCommentRequest $request
     * @param Post $post
     * @param Comment $comment
     * @return array
     */
    public function deleteComment(DeleteCommentRequest $request, Post $post, Comment $comment): array
    {
        return $this->service->deleteComment($post, $comment);
    }
}
