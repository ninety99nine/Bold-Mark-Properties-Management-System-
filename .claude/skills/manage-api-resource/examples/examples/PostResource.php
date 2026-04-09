<?php

namespace App\Http\Resources;

use App\Services\AuthService;
use Illuminate\Http\Request;

/**
 * Full-featured example: scalars, computed fields, counts, relationships,
 * pivot, and permission checks. Adapt model/service names to your project.
 */
class PostResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'slug'        => $this->slug,
            'body'        => $this->body,
            'status'      => $this->status,
            'featured'    => $this->featured ? true : false,
            'extra_meta'  => $this->extra_meta ?? null,
            'created_at'  => $this->created_at?->toDateTimeString(),
            'updated_at'  => $this->updated_at?->toDateTimeString(),

            'is_published' => $this->published_at !== null,
            'published_at' => $this->published_at?->toDateTimeString(),

            // Countable attributes
            'comments_count' => $this->whenCounted('comments'),
            'likes_count'    => $this->whenCounted('likes'),
            'tags_count'     => $this->whenCounted('tags'),

            // Relationships
            'category'   => CategoryResource::make($this->whenLoaded('category')),
            'tags'       => TagResource::collection($this->whenLoaded('tags') ?? []),
            'comments'   => CommentResource::collection($this->whenLoaded('comments')),
            'created_by' => $this->whenLoaded('author', fn() => [
                'id'         => $this->author->id,
                'name'       => $this->author->name,
                'first_name' => $this->author->first_name,
            ]),

            // Pivot
            'post_user' => $this->post_user ? PostUserResource::make($this->post_user) : null,

            // Permissions
            'can_edit_post'   => $request->user()
                ? resolve(AuthService::class)->hasPermission($request->user(), 'post.edit', $this->id)
                : false,
            'can_delete_post' => $request->user()
                ? resolve(AuthService::class)->hasPermission($request->user(), 'post.delete', $this->id)
                : false,
        ];
    }
}
