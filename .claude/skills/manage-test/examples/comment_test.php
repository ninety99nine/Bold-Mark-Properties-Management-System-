<?php

use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Models\Comment;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =============================================================================
// Show Comments
// =============================================================================

// CommentPolicy::viewAny() → Tier 1 (return true) — no 403 test needed.

it('blocks unauthenticated requests to show comments', function (): void {
    $post = Post::factory()->create();

    $this->getJson(route('show.comments', ['post' => $post]))
        ->assertUnauthorized();
});

it('returns 404 when showing comments for a non-existent post', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('show.comments', ['post' => 'non-existent-id']))
        ->assertNotFound();
});

it('returns an empty list when the post has no comments', function (): void {
    $user = User::factory()->create();
    $post = commentPost($user);

    $this->actingAs($user)
        ->getJson(route('show.comments', ['post' => $post]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns only comments belonging to the requested post', function (): void {
    $user      = User::factory()->create();
    $post      = commentPost($user);
    $otherPost = Post::factory()->create();

    Comment::factory()->count(2)->create(['post_id' => $post->id]);
    Comment::factory()->count(3)->create(['post_id' => $otherPost->id]);

    $this->actingAs($user)
        ->getJson(route('show.comments', ['post' => $post]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns the correct comment list structure', function (): void {
    $user = User::factory()->create();
    $post = commentPost($user);
    Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->getJson(route('show.comments', ['post' => $post]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'body', 'post_id', 'status', 'created_at', 'updated_at'],
            ],
        ]);
});

// =============================================================================
// Show Comment
// =============================================================================

// CommentPolicy::view() → Tier 1 (return true) — no 403 test needed.

it('blocks unauthenticated requests to show a comment', function (): void {
    $post    = Post::factory()->create();
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->getJson(route('show.comment', ['post' => $post, 'comment' => $comment]))
        ->assertUnauthorized();
});

it('returns 404 when the comment does not exist', function (): void {
    $user = User::factory()->create();
    $post = commentPost($user);

    $this->actingAs($user)
        ->getJson(route('show.comment', ['post' => $post, 'comment' => 'non-existent-id']))
        ->assertNotFound();
});

it('returns 404 when the comment does not belong to the post', function (): void {
    $user         = User::factory()->create();
    $post         = commentPost($user);
    $otherPost    = Post::factory()->create();
    $otherComment = Comment::factory()->create(['post_id' => $otherPost->id]);

    $this->actingAs($user)
        ->getJson(route('show.comment', ['post' => $post, 'comment' => $otherComment]))
        ->assertNotFound();
});

it('returns the correct comment structure', function (): void {
    $user    = User::factory()->create();
    $post    = commentPost($user);
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->getJson(route('show.comment', ['post' => $post, 'comment' => $comment]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'body', 'post_id', 'status', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('data.id', $comment->id)
        ->assertJsonPath('data.post_id', $post->id);
});

// =============================================================================
// Create Comment
// =============================================================================

// CommentPolicy::create() → Tier 1 (return true) — no 403 test needed.

it('blocks unauthenticated requests to create a comment', function (): void {
    $post = Post::factory()->create();

    $this->postJson(route('create.comment', ['post' => $post]))
        ->assertUnauthorized();
});

it('requires a body to create a comment', function (): void {
    $user = User::factory()->create();
    $post = commentPost($user);

    $this->actingAs($user)
        ->postJson(route('create.comment', ['post' => $post]), validCommentPayload(['body' => '']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('requires body to be a string', function (): void {
    $user = User::factory()->create();
    $post = commentPost($user);

    $this->actingAs($user)
        ->postJson(route('create.comment', ['post' => $post]), validCommentPayload(['body' => 123]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('requires body not to exceed 2000 characters', function (): void {
    $user = User::factory()->create();
    $post = commentPost($user);

    $this->actingAs($user)
        ->postJson(route('create.comment', ['post' => $post]), validCommentPayload(['body' => str_repeat('x', 2001)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('allows parent_id to be omitted', function (): void {
    $user = User::factory()->create();
    $post = commentPost($user);

    $this->actingAs($user)
        ->postJson(route('create.comment', ['post' => $post]), validCommentPayload(['parent_id' => null]))
        ->assertCreated();
});

it('rejects a parent_id belonging to a different post', function (): void {
    $user         = User::factory()->create();
    $post         = commentPost($user);
    $otherPost    = Post::factory()->create();
    $otherComment = Comment::factory()->create(['post_id' => $otherPost->id]);

    $this->actingAs($user)
        ->postJson(route('create.comment', ['post' => $post]), validCommentPayload([
            'parent_id' => $otherComment->id,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

it('accepts a parent_id belonging to the same post', function (): void {
    $user            = User::factory()->create();
    $post            = commentPost($user);
    $parentComment   = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->postJson(route('create.comment', ['post' => $post]), validCommentPayload([
            'parent_id' => $parentComment->id,
        ]))
        ->assertCreated();
});

it('creates a comment successfully', function (): void {
    $user = User::factory()->create();
    $post = commentPost($user);

    $this->actingAs($user)
        ->postJson(route('create.comment', ['post' => $post]), validCommentPayload())
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'body', 'post_id', 'status', 'created_at', 'updated_at'],
        ]);

    assertDatabaseHas('comments', ['body' => 'Great post!', 'post_id' => $post->id]);
});

// =============================================================================
// Update Comment
// =============================================================================

it('blocks unauthenticated requests to update a comment', function (): void {
    $post    = Post::factory()->create();
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->putJson(route('update.comment', ['post' => $post, 'comment' => $comment]))
        ->assertUnauthorized();
});

it('blocks users without comment.update permission from updating a comment', function (): void {
    $user    = User::factory()->create();
    $post    = commentPost($user);
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->putJson(route('update.comment', ['post' => $post, 'comment' => $comment]), validCommentUpdatePayload())
        ->assertForbidden();
});

it('returns 404 when the comment to update does not belong to the post', function (): void {
    $user         = User::factory()->create();
    $post         = commentPostWithPermission($user, 'comment.update');
    $otherPost    = Post::factory()->create();
    $otherComment = Comment::factory()->create(['post_id' => $otherPost->id]);

    $this->actingAs($user)
        ->putJson(route('update.comment', ['post' => $post, 'comment' => $otherComment]), validCommentUpdatePayload())
        ->assertNotFound();
});

it('requires body to be a string on update when provided', function (): void {
    $user    = User::factory()->create();
    $post    = commentPostWithPermission($user, 'comment.update');
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->putJson(route('update.comment', ['post' => $post, 'comment' => $comment]), validCommentUpdatePayload(['body' => 123]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('requires body not to exceed 2000 characters on update', function (): void {
    $user    = User::factory()->create();
    $post    = commentPostWithPermission($user, 'comment.update');
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->putJson(route('update.comment', ['post' => $post, 'comment' => $comment]), validCommentUpdatePayload(['body' => str_repeat('x', 2001)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

it('allows body to be omitted on update', function (): void {
    $user    = User::factory()->create();
    $post    = commentPostWithPermission($user, 'comment.update');
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    // body is 'sometimes' — omitting it must not trigger validation
    $this->actingAs($user)
        ->putJson(route('update.comment', ['post' => $post, 'comment' => $comment]), [])
        ->assertOk();
});

it('updates a comment successfully', function (): void {
    $user    = User::factory()->create();
    $post    = commentPostWithPermission($user, 'comment.update');
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->putJson(route('update.comment', ['post' => $post, 'comment' => $comment]), [
            'body' => 'Updated body.',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'body', 'post_id', 'status', 'created_at', 'updated_at'],
        ]);

    assertDatabaseHas('comments', ['id' => $comment->id, 'body' => 'Updated body.']);
});

// =============================================================================
// Delete Comments (Bulk)
// =============================================================================

it('blocks unauthenticated requests to delete comments', function (): void {
    $post = Post::factory()->create();

    $this->deleteJson(route('delete.comments', ['post' => $post]))
        ->assertUnauthorized();
});

it('blocks users without comment.delete permission from bulk deleting comments', function (): void {
    $user    = User::factory()->create();
    $post    = commentPost($user);
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->deleteJson(route('delete.comments', ['post' => $post]), ['comment_ids' => [$comment->id]])
        ->assertForbidden();
});

it('requires comment_ids to bulk delete comments', function (): void {
    $user = User::factory()->create();
    $post = commentPostWithPermission($user, 'comment.delete');

    $this->actingAs($user)
        ->deleteJson(route('delete.comments', ['post' => $post]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['comment_ids']);
});

it('requires comment_ids to be an array', function (): void {
    $user = User::factory()->create();
    $post = commentPostWithPermission($user, 'comment.delete');

    $this->actingAs($user)
        ->deleteJson(route('delete.comments', ['post' => $post]), ['comment_ids' => 'not-an-array'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['comment_ids']);
});

it('requires at least one comment id', function (): void {
    $user = User::factory()->create();
    $post = commentPostWithPermission($user, 'comment.delete');

    $this->actingAs($user)
        ->deleteJson(route('delete.comments', ['post' => $post]), ['comment_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['comment_ids']);
});

it('requires each comment id to be a valid uuid', function (): void {
    $user = User::factory()->create();
    $post = commentPostWithPermission($user, 'comment.delete');

    $this->actingAs($user)
        ->deleteJson(route('delete.comments', ['post' => $post]), ['comment_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['comment_ids.0']);
});

it('bulk deletes comments successfully', function (): void {
    $user      = User::factory()->create();
    $post      = commentPostWithPermission($user, 'comment.delete');
    $commentA  = Comment::factory()->create(['post_id' => $post->id]);
    $commentB  = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->deleteJson(route('delete.comments', ['post' => $post]), [
            'comment_ids' => [$commentA->id, $commentB->id],
        ])
        ->assertOk();

    assertDatabaseMissing('comments', ['id' => $commentA->id]);
    assertDatabaseMissing('comments', ['id' => $commentB->id]);
});

it('does not delete comments belonging to other posts', function (): void {
    $user         = User::factory()->create();
    $post         = commentPostWithPermission($user, 'comment.delete');
    $otherPost    = Post::factory()->create();
    $otherComment = Comment::factory()->create(['post_id' => $otherPost->id]);

    $this->actingAs($user)
        ->deleteJson(route('delete.comments', ['post' => $post]), [
            'comment_ids' => [$otherComment->id],
        ])
        ->assertOk();

    assertDatabaseHas('comments', ['id' => $otherComment->id]);
});

// =============================================================================
// Delete Comment
// =============================================================================

it('blocks unauthenticated requests to delete a comment', function (): void {
    $post    = Post::factory()->create();
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->deleteJson(route('delete.comment', ['post' => $post, 'comment' => $comment]))
        ->assertUnauthorized();
});

it('blocks users without comment.delete permission from deleting a comment', function (): void {
    $user    = User::factory()->create();
    $post    = commentPost($user);
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->deleteJson(route('delete.comment', ['post' => $post, 'comment' => $comment]))
        ->assertForbidden();
});

it('returns 404 when the comment to delete does not exist', function (): void {
    $user = User::factory()->create();
    $post = commentPostWithPermission($user, 'comment.delete');

    $this->actingAs($user)
        ->deleteJson(route('delete.comment', ['post' => $post, 'comment' => 'non-existent-id']))
        ->assertNotFound();
});

it('returns 404 when deleting a comment that does not belong to the post', function (): void {
    $user         = User::factory()->create();
    $post         = commentPostWithPermission($user, 'comment.delete');
    $otherPost    = Post::factory()->create();
    $otherComment = Comment::factory()->create(['post_id' => $otherPost->id]);

    $this->actingAs($user)
        ->deleteJson(route('delete.comment', ['post' => $post, 'comment' => $otherComment]))
        ->assertNotFound();
});

it('deletes a comment successfully', function (): void {
    $user    = User::factory()->create();
    $post    = commentPostWithPermission($user, 'comment.delete');
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    $this->actingAs($user)
        ->deleteJson(route('delete.comment', ['post' => $post, 'comment' => $comment]))
        ->assertOk();

    assertDatabaseMissing('comments', ['id' => $comment->id]);
});

// =============================================================================
// Helpers
// =============================================================================

function validCommentPayload(array $overrides = []): array
{
    return array_merge([
        'body'           => 'Great post!',
        'parent_id'      => null,
        'attachment_url' => null,
    ], $overrides);
}

function validCommentUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'body'           => 'Updated body.',
        'attachment_url' => null,
    ], $overrides);
}

function commentPost(User $user): Post
{
    $post = Post::factory()->create();

    $post->members()->attach($user->id, [
        'joined_at'    => now(),
        'invited_at'   => null,
        'last_seen_at' => now(),
        'role_id'      => null,
    ]);

    return $post;
}

function commentPostWithPermission(User $user, string $permission): Post
{
    $post = Post::factory()->create();

    $role = Role::create([
        'name'         => 'role-' . $post->id,
        'guard_name'   => 'sanctum',
        'workspace_id' => $post->workspace_id,
    ]);

    $role->givePermissionTo(
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum'])
    );

    $post->members()->attach($user->id, [
        'joined_at'    => now(),
        'invited_at'   => null,
        'last_seen_at' => now(),
        'role_id'      => $role->id,
    ]);

    return $post;
}
