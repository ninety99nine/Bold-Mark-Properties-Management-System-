<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\PostService;
use App\Http\Resources\PostResource;
use App\Http\Resources\PostResources;
use App\Http\Requests\Post\ShowPostRequest;
use App\Http\Requests\Post\ShowPostsRequest;
use App\Http\Requests\Post\CreatePostRequest;
use App\Http\Requests\Post\DeletePostRequest;
use App\Http\Requests\Post\DeletePostsRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Requests\Post\UploadPostThumbnailRequest;

class PostController extends Controller
{
    /**
     * @var PostService
     */
    protected $service;

    /**
     * PostController constructor.
     *
     * @param PostService $service
     */
    public function __construct(PostService $service)
    {
        $this->service = $service;
    }

    /**
     * Show posts.
     *
     * @param ShowPostsRequest $request
     * @return PostResources|array
     */
    public function showPosts(ShowPostsRequest $request): PostResources|array
    {
        return $this->service->showPosts($request->validated());
    }

    /**
     * Create post.
     *
     * @param CreatePostRequest $request
     * @return array
     */
    public function createPost(CreatePostRequest $request): array
    {
        return $this->service->createPost($request->validated());
    }

    /**
     * Delete posts.
     *
     * @param DeletePostsRequest $request
     * @return array
     */
    public function deletePosts(DeletePostsRequest $request): array
    {
        $postIds = $request->input('post_ids', []);
        return $this->service->deletePosts($postIds);
    }

    /**
     * Show post.
     *
     * @param ShowPostRequest $request
     * @param Post $post
     * @return PostResource
     */
    public function showPost(ShowPostRequest $request, Post $post): PostResource
    {
        return $this->service->showPost($post);
    }

    /**
     * Update post.
     *
     * @param UpdatePostRequest $request
     * @param Post $post
     * @return array
     */
    public function updatePost(UpdatePostRequest $request, Post $post): array
    {
        return $this->service->updatePost($post, $request->validated());
    }

    /**
     * Upload post thumbnail.
     *
     * @param UploadPostThumbnailRequest $request
     * @param Post $post
     * @return array
     */
    public function uploadPostThumbnail(UploadPostThumbnailRequest $request, Post $post): array
    {
        return $this->service->uploadPostThumbnail($post, $request->file('thumbnail'));
    }

    /**
     * Delete post.
     *
     * @param DeletePostRequest $request
     * @param Post $post
     * @return array
     */
    public function deletePost(DeletePostRequest $request, Post $post): array
    {
        return $this->service->deletePost($post);
    }
}
