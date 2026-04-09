<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Demonstrates: column-aligned scalars, inline partial whenLoaded() with
 * closure to expose limited author fields, mixed single/collection relationships.
 */
class CommentResource extends BaseResource
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
            'id'         => $this->id,
            'post_id'    => $this->post_id,
            'parent_id'  => $this->parent_id ?? null,
            'body'       => $this->body,
            'status'     => $this->status ?? 'pending',
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            // Countable attributes
            'replies_count' => $this->whenCounted('replies'),

            // Relationships
            'post'    => PostResource::make($this->whenLoaded('post')),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'author'  => $this->whenLoaded('author', fn() => [
                'id'         => $this->author->id,
                'name'       => $this->author->name,
                'first_name' => $this->author->first_name,
            ]),
        ];
    }
}
