<?php

use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// =============================================================================
// Show Posts
// =============================================================================

it('blocks unauthenticated requests to show posts', function (): void {
    $this->getJson(route('show.posts'))
        ->assertUnauthorized();
});

it('returns an empty list when the user has no posts', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('show.posts'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns only posts the authenticated user belongs to', function (): void {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    attachUserToWorkspace($user, Post::factory()->create());
    attachUserToWorkspace($user, Post::factory()->create());
    attachUserToWorkspace($other, Post::factory()->create());

    $this->actingAs($user)
        ->getJson(route('show.posts'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns the correct post list structure', function (): void {
    $user = User::factory()->create();
    attachUserToWorkspace($user, Post::factory()->create());

    $this->actingAs($user)
        ->getJson(route('show.posts'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'slug', 'status', 'thumbnail_url', 'created_at', 'updated_at'],
            ],
        ]);
});

// =============================================================================
// Show Post
// =============================================================================

it('blocks unauthenticated requests to show a post', function (): void {
    $post = Post::factory()->create();

    $this->getJson(route('show.post', ['post' => $post]))
        ->assertUnauthorized();
});

it('returns 404 when showing a non-existent post', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('show.post', ['post' => 'non-existent-id']))
        ->assertNotFound();
});

it('returns the correct post structure', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    attachUserToWorkspace($user, $post);

    $this->actingAs($user)
        ->getJson(route('show.post', ['post' => $post]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'status', 'thumbnail_url', 'created_at', 'updated_at'],
        ])
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonPath('data.title', $post->title);
});

// =============================================================================
// Create Post
// =============================================================================

// PostPolicy::create() → Tier 1 (return true) — no 403 test needed.

it('blocks unauthenticated requests to create a post', function (): void {
    $this->postJson(route('create.post'), validPostPayload())
        ->assertUnauthorized();
});

it('requires a title to create a post', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('create.post'), validPostPayload(['title' => '']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('requires title to be a string', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('create.post'), validPostPayload(['title' => 123]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('requires title not to exceed 255 characters', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('create.post'), validPostPayload(['title' => str_repeat('x', 256)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('allows thumbnail to be omitted when creating a post', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('create.post'), validPostPayload(['thumbnail' => null]))
        ->assertCreated();
});

it('rejects a thumbnail with an unsupported mime type', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('create.post'), validPostPayload([
            'thumbnail' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['thumbnail']);
});

it('rejects a thumbnail exceeding 5MB', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('create.post'), validPostPayload([
            'thumbnail' => UploadedFile::fake()->create('image.png', 5121, 'image/png'),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['thumbnail']);
});

it('creates a post successfully', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('create.post'), validPostPayload())
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'status', 'thumbnail_url', 'created_at', 'updated_at'],
        ]);

    assertDatabaseHas('posts', ['title' => 'Test Post', 'status' => 'draft']);
});

it('attaches the author to the post on creation', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('create.post'), validPostPayload())
        ->assertCreated();

    assertDatabaseHas('posts', [
        'id'        => $response->json('data.id'),
        'author_id' => $user->id,
    ]);
});

// =============================================================================
// Upload Post Thumbnail
// =============================================================================

it('blocks unauthenticated requests to upload a thumbnail', function (): void {
    $post = Post::factory()->create();

    $this->postJson(route('upload.post.thumbnail', ['post' => $post]))
        ->assertUnauthorized();
});

it('blocks users without post.update permission from uploading a thumbnail', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    $post = postWithPermission($user);

    $this->actingAs($user)
        ->postJson(route('upload.post.thumbnail', ['post' => $post]), [
            'thumbnail' => UploadedFile::fake()->image('thumb.png'),
        ])
        ->assertForbidden();
});

it('requires a thumbnail file to upload', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    $post = postWithPermission($user, 'post.update');

    $this->actingAs($user)
        ->postJson(route('upload.post.thumbnail', ['post' => $post]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['thumbnail']);
});

it('rejects a non-image file as thumbnail', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    $post = postWithPermission($user, 'post.update');

    $this->actingAs($user)
        ->postJson(route('upload.post.thumbnail', ['post' => $post]), [
            'thumbnail' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['thumbnail']);
});

it('rejects a thumbnail exceeding 2MB', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    $post = postWithPermission($user, 'post.update');

    $this->actingAs($user)
        ->postJson(route('upload.post.thumbnail', ['post' => $post]), [
            'thumbnail' => UploadedFile::fake()->create('thumb.png', 2049, 'image/png'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['thumbnail']);
});

it('uploads the post thumbnail successfully', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    $post = postWithPermission($user, 'post.update');

    $this->actingAs($user)
        ->postJson(route('upload.post.thumbnail', ['post' => $post]), [
            'thumbnail' => UploadedFile::fake()->image('thumb.png'),
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'thumbnail_url']]);
});

// =============================================================================
// Delete Posts (Bulk)
// =============================================================================

it('blocks unauthenticated requests to delete posts', function (): void {
    $post = Post::factory()->create();

    $this->deleteJson(route('delete.posts'), ['post_ids' => [$post->id]])
        ->assertUnauthorized();
});

it('blocks users without post.delete permission on all requested posts', function (): void {
    $user  = User::factory()->create();
    $postA = postWithPermission($user, 'post.delete');
    $postB = Post::factory()->create();
    attachUserToWorkspace($user, $postB);

    $this->actingAs($user)
        ->deleteJson(route('delete.posts'), ['post_ids' => [$postA->id, $postB->id]])
        ->assertForbidden();
});

it('requires post_ids to delete posts', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('delete.posts'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['post_ids']);
});

it('requires post_ids to be an array', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('delete.posts'), ['post_ids' => 'not-an-array'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['post_ids']);
});

it('requires at least one post id', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('delete.posts'), ['post_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['post_ids']);
});

it('requires each post id to be a valid uuid', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('delete.posts'), ['post_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['post_ids.0']);
});

it('deletes multiple posts successfully', function (): void {
    $user  = User::factory()->create();
    $postA = postWithPermission($user, 'post.delete');
    $postB = postWithPermission($user, 'post.delete');

    $this->actingAs($user)
        ->deleteJson(route('delete.posts'), ['post_ids' => [$postA->id, $postB->id]])
        ->assertOk();

    assertDatabaseMissing('posts', ['id' => $postA->id]);
    assertDatabaseMissing('posts', ['id' => $postB->id]);
});

// =============================================================================
// Delete Post
// =============================================================================

it('blocks unauthenticated requests to delete a post', function (): void {
    $post = Post::factory()->create();

    $this->deleteJson(route('delete.post', ['post' => $post]))
        ->assertUnauthorized();
});

it('blocks users without post.delete permission from deleting a post', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    attachUserToWorkspace($user, $post);

    $this->actingAs($user)
        ->deleteJson(route('delete.post', ['post' => $post]))
        ->assertForbidden();
});

it('returns 404 when deleting a non-existent post', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('delete.post', ['post' => 'non-existent-id']))
        ->assertNotFound();
});

it('deletes a post successfully', function (): void {
    $user = User::factory()->create();
    $post = postWithPermission($user, 'post.delete');

    $this->actingAs($user)
        ->deleteJson(route('delete.post', ['post' => $post]))
        ->assertOk();

    assertDatabaseMissing('posts', ['id' => $post->id]);
});

// =============================================================================
// Helpers
// =============================================================================

function validPostPayload(array $overrides = []): array
{
    return array_merge([
        'title'     => 'Test Post',
        'body'      => 'Post body content.',
        'status'    => 'draft',
        'thumbnail' => null,
    ], $overrides);
}

function attachUserToWorkspace(User $user, Post $post, ?string $roleId = null): void
{
    $post->members()->attach($user->id, [
        'joined_at'    => now(),
        'invited_at'   => null,
        'last_seen_at' => now(),
        'role_id'      => $roleId,
    ]);
}

function postWithPermission(User $user, ?string $permission = null): Post
{
    $post = Post::factory()->create();

    $role = Role::create([
        'name'         => 'role-' . $post->id,
        'guard_name'   => 'sanctum',
        'workspace_id' => $post->workspace_id,
    ]);

    if ($permission) {
        $role->givePermissionTo(
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum'])
        );
    }

    $post->members()->attach($user->id, [
        'joined_at'    => now(),
        'invited_at'   => null,
        'last_seen_at' => now(),
        'role_id'      => $role->id,
    ]);

    return $post;
}
