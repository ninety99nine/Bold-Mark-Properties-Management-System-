<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy extends BasePolicy
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
     * Determine whether the user can view any posts.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the post.
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function view(User $user, Post $post): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create posts.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the post.
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function update(User $user, Post $post): bool
    {
        return $this->authService->hasPermission($user, 'post.update', $post->workspace_id);
    }

    /**
     * Determine whether the user can delete any posts.
     *
     * @param User $user
     * @return bool
     */
    public function deleteAny(User $user): bool
    {
        $postIds = request()->input('post_ids', []);
        foreach ($postIds as $postId) {
            if (!$this->authService->hasPermission($user, 'post.delete', $postId)) {
                return false;
            }
        }
        return !empty($postIds);
    }

    /**
     * Determine whether the user can delete the post.
     *
     * @param User $user
     * @param Post $post
     * @return bool
     */
    public function delete(User $user, Post $post): bool
    {
        return $this->authService->hasPermission($user, 'post.delete', $post->workspace_id);
    }
}
