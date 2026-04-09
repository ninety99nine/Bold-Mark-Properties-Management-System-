<?php

namespace App\Services;

use Exception;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use App\Enums\PostStatus;
use App\Enums\MediaFolderName;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostResources;

class PostService extends BaseService
{
    /**
     * Show posts.
     *
     * @param array $data
     * @return PostResources|array
     */
    public function showPosts(array $data): PostResources|array
    {
        /** @var User $user */
        $user = Auth::user();

        $query = $user->posts();
        if (!request()->has('_sort')) $query = $query->latest();

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create post.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createPost(array $data): array
    {
        /** @var User $user */
        $user = Auth::user();

        $postData = collect($data)->only(['title', 'slug', 'body', 'status'])->toArray();

        // 1. Create the post
        $post = Post::create([
            ...$postData,
            'author_id'    => $user->id,
            'workspace_id' => $user->workspace_id,
        ]);

        // 2. Attach initial default tags if provided
        if (!empty($data['tag_ids'] ?? [])) {
            $post->tags()->sync($data['tag_ids']);
        }

        // 3. Upload featured image if provided
        if (!empty($data['featured_image'] ?? null)) {
            (new MediaFileService())->createMediaFile([
                'file'               => $data['featured_image'],
                'mediable_type'      => 'post',
                'mediable_id'        => $post->id,
                'upload_folder_name' => MediaFolderName::POST_IMAGE->value,
            ]);
        }

        // 4. Notify workspace members
        (new NotificationService())->notifyNewPost($post, $user);

        return $this->showCreatedResource($post);
    }

    /**
     * Delete posts.
     *
     * @param array $postIds
     * @return array
     * @throws Exception
     */
    public function deletePosts(array $postIds): array
    {
        $posts = Post::whereIn('id', $postIds)->with(['mediaFiles'])->get();

        if ($total = $posts->count()) {
            foreach ($posts as $post) {
                $this->deletePost($post);
            }
            return ['message' => $total . ($total === 1 ? ' Post' : ' Posts') . ' deleted'];
        }

        throw new Exception('No Posts deleted');
    }

    /**
     * Show post.
     *
     * @param Post $post
     * @return PostResource
     */
    public function showPost(Post $post): PostResource
    {
        return $this->showResource($post);
    }

    /**
     * Update post.
     *
     * @param Post $post
     * @param array $data
     * @return array
     */
    public function updatePost(Post $post, array $data): array
    {
        $post->update($data);
        return $this->showUpdatedResource($post);
    }

    /**
     * Upload post thumbnail.
     *
     * @param Post $post
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    public function uploadPostThumbnail(Post $post, $file): array
    {
        (new MediaFileService())->createMediaFile([
            'file'               => $file,
            'mediable_type'      => 'post',
            'mediable_id'        => $post->id,
            'upload_folder_name' => MediaFolderName::POST_IMAGE->value,
        ]);

        $post->load('featuredImage');
        return $this->showUpdatedResource($post);
    }

    /**
     * Delete post.
     *
     * @param Post $post
     * @return array
     */
    public function deletePost(Post $post): array
    {
        $mediaFileService = new MediaFileService();

        $post->load('mediaFiles');
        foreach ($post->mediaFiles as $mediaFile) {
            $mediaFileService->deleteMediaFile($mediaFile);
        }

        $deleted = $post->forceDelete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Post deleted' : 'Post delete unsuccessful',
        ];
    }

    /**
     * Accept workspace invitations for a newly registered user.
     *
     * @param User $user
     * @return void
     */
    public function acceptWorkspaceInvitations(User $user): void
    {
        $invitationFirstName = DB::table('workspace_user')
            ->where('email', $user->email)
            ->whereNull('user_id')
            ->whereNotNull('first_name')
            ->where('first_name', '!=', '')
            ->value('first_name');

        if ($invitationFirstName && empty(trim($user->first_name ?? ''))) {
            $user->update(['first_name' => trim($invitationFirstName)]);
        }

        DB::table('workspace_user')->where('email', $user->email)->update([
            'email'      => null,
            'joined_at'  => now(),
            'first_name' => null,
            'user_id'    => $user->id,
        ]);
    }
}
