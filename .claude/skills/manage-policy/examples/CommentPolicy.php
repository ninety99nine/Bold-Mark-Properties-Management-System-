<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

class CommentPolicy extends BasePolicy
{
    /**
     * Grant all permissions to super admins.
     *
     * @param User $user
     * @param string $ability
     * @return bool|null
     */
    public function before(User $user, string $ability): bool|null
    {
        return $this->authService->isSuperAdmin($user) ? true : null;
    }

    /**
     * Determine whether the user can view any comments.
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function viewAny(User $user, Post $post): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the comment.
     *
     * @param User $user
     * @param Post $post
     * @param Comment $comment
     * @return bool
     */
    public function view(User $user, Post $post, Comment $comment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create comments.
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function create(User $user, Post $post): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the comment.
     *
     * @param User $user
     * @param Post $post
     * @param Comment $comment
     * @return bool
     */
    public function update(User $user, Post $post, Comment $comment): bool
    {
        return $this->authService->hasPermission($user, 'comment.update', $post->id);
    }

    /**
     * Determine whether the user can delete any comments.
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function deleteAny(User $user, Post $post): bool
    {
        return $this->authService->hasPermission($user, 'comment.delete', $post->id);
    }

    /**
     * Determine whether the user can delete the comment.
     *
     * @param User $user
     * @param Post $post
     * @param Comment $comment
     * @return bool
     */
    public function delete(User $user, Post $post, Comment $comment): bool
    {
        if ($comment->created_by === $user->id) {
            return true;
        }
        return $this->authService->hasPermission($user, 'comment.delete', $post->id);
    }
}
