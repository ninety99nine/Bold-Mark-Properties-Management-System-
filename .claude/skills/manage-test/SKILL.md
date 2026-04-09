---
name: create-test
description: Create or update Laravel Pest feature test files to match the team's strict code design pattern. Use this skill whenever you need to create a new test file, add tests to an existing file, or refactor tests to follow project conventions — including file location, naming, section structure, auth/permission scaffolding, assertion style, and test ordering. Trigger this skill for any task involving Laravel tests, even if the user just says "write tests for this controller" or "add a test for this route."
---

# Create Test

Create a Laravel Pest feature test file to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing any test, gather context by reading the codebase directly.

### 1. Find the route file
Read `routes/api/{resource}.php` to extract every route name and its HTTP method. Route names determine which `route('name', [...])` calls to use.

### 2. Find the Form Requests
Read `app/Http/Requests/{Resource}/` to know:
- The policy ability called in `authorize()` → determines what permission the user needs and whether a 403 test is required
- The `rules()` array → every required field and constraint that needs a validation failure test

### 3. Find the Policy
Read `app/Policies/{Resource}Policy.php` to confirm the permission string used in `hasPermission()` and whether each method is Tier 1 (`return true`) or Tier 2 (permission-gated).

### 4. Find the Resource
Read `app/Http/Resources/{Resource}Resource.php` to know the response shape for `assertJsonStructure()`.

### 5. Find or create the Factory
Check `database/factories/{Resource}Factory.php`. If it doesn't exist, create it before writing the test.

### 6. Understand the membership/permission scaffolding pattern
Read existing test files in `tests/Feature/` to understand how users are attached to the parent resource (workspace, team, organisation, etc.) and how permissions are granted. Mirror that pattern exactly in helpers.

### 7. Only ask if truly blocked
If the route, request, and policy all don't exist yet, create them first using the other skills. Only ask if something genuinely cannot be inferred.

---

## Strict Rules

1. **Pest feature tests only** — all tests live in `tests/Feature/{Resource}/` and use Pest syntax. No PHPUnit class-based tests.
2. **One test file per resource** — `{resource}_test.php` (e.g. `post_test.php`, `comment_test.php`). All actions for that resource live in one file.
3. **Always `uses(RefreshDatabase::class)`** — at the top of every test file, after the imports.
4. **Test descriptions use natural language** — `it('blocks unauthenticated requests to show posts')`, `it('requires a title')`. No `test_` prefixes, no snake_case descriptions.
5. **Always test unauthenticated (401) first within each section** — it's the first `it()` block for every action.
6. **Always test unauthorized (403) second within each section** — for any action that is Tier 2 policy-gated. Omit only if the policy method is pure Tier 1 (`return true`). Leave a comment explaining why: `// PolicyName::method() → Tier 1 (return true) — no 403 test needed.`
7. **One assertion focus per `it()` block** — each test covers one specific condition. Never combine two validation rules in a single block.
8. **Use route names** — always `route('route.name', ['param' => $model])`, never hardcoded `/api/...` URLs.
9. **Use `actingAs()`** — never manually create or pass tokens.
10. **No `beforeEach()`** — since one file covers multiple actions with different setup needs, models are always created locally inside each `it()` block. Never use `beforeEach()`.
11. **File-scoped helper functions** — `validPayload()`, `postWithPermission()`, and similar setup helpers go at the bottom of the file under a `// Helpers` divider. These are plain functions, not methods or closures.
12. **Helper function names must be unique per file** — since Pest helper functions are globally visible across the test suite, name them specifically: `postWithPermission()`, `commentPost()`, `commentPostWithPermission()`. Never use a generic name like `createResourceWithUser()` that would collide across files.
13. **Assert DB side-effects** — successful create/update/delete tests must include `assertDatabaseHas`, `assertDatabaseMissing`, or `assertDatabaseCount`.
14. **Assert response structure** — successful show tests must include `assertJsonStructure` matching the resource's `toArray()` keys.
15. **Closure return types** — all `it()` and helper function closures must declare `): void`.
16. **No unused imports.**

---

## File Location and Naming

One file per resource. All actions for that resource live inside it, separated by section dividers.

```
tests/Feature/Post/post_test.php
tests/Feature/Comment/comment_test.php
tests/Feature/Order/order_test.php
...
```

---

## Section Dividers

Actions within the file are separated with a full-width section header:

```php
// =============================================================================
// Show Posts
// =============================================================================
```

---

## Test Order Within Each Section (Strict)

```
1. it('blocks unauthenticated requests to {action} {resource}')      — 401, always first
2. it('blocks users without permission from {action}ing {resource}')  — 403, second (skip if Tier 1)
3. it('returns 404 ...')                                              — 404, if applicable
4. it('requires {field}')                                            — 422, one per required field
5. it('requires {field} to be {constraint}')                         — 422, one per format/type rule
6. it('{action}s {resource} successfully')                           — 200/201, happy path with DB assertion
7. it('{edge case description}')                                      — isolation, empty results, etc.
```

---

## File Structure

```php
<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =============================================================================
// Show {Resources}
// =============================================================================

// {Resource}Policy::viewAny() → Tier 1 (return true) — no 403 test needed.

it('blocks unauthenticated requests to show {resources}', function (): void {
    $post = Post::factory()->create();

    $this->getJson(route('show.{resources}', ['post' => $post]))
        ->assertUnauthorized();
});

it('returns the correct {resource} list structure', function (): void {
    $user = User::factory()->create();
    $post = {resource}Post($user);

    $this->actingAs($user)
        ->getJson(route('show.{resources}', ['post' => $post]))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['*' => ['id', ...]],
        ]);
});

// =============================================================================
// Create {Resource}
// =============================================================================

it('blocks unauthenticated requests to create a {resource}', function (): void {
    $post = Post::factory()->create();

    $this->postJson(route('create.{resource}', ['post' => $post]))
        ->assertUnauthorized();
});

it('blocks users without permission from creating a {resource}', function (): void {
    $user = User::factory()->create();
    $post = {resource}Post($user); // no permission

    $this->actingAs($user)
        ->postJson(route('create.{resource}', ['post' => $post]), valid{Resource}Payload())
        ->assertForbidden();
});

it('creates a {resource} successfully', function (): void {
    $user = User::factory()->create();
    $post = {resource}PostWithPermission($user, 'permission.name');

    $this->actingAs($user)
        ->postJson(route('create.{resource}', ['post' => $post]), valid{Resource}Payload())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', ...]]);

    assertDatabaseHas('{resources}', ['field' => 'value', 'post_id' => $post->id]);
});

// =============================================================================
// Helpers
// =============================================================================

function valid{Resource}Payload(array $overrides = []): array
{
    return array_merge([
        'field' => 'value',
    ], $overrides);
}

function {resource}Post(User $user): Post
{
    $post = Post::factory()->create();

    $post->members()->attach($user->id, [
        'joined_at'    => now(),
        'role_id'      => null,
    ]);

    return $post;
}

function {resource}PostWithPermission(User $user, string $permission): Post
{
    $post = Post::factory()->create();

    $role = Role::create([
        'name'       => 'role-' . $post->id,
        'guard_name' => 'sanctum',
        'workspace_id' => $post->workspace_id,
    ]);

    $role->givePermissionTo(
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum'])
    );

    $post->members()->attach($user->id, [
        'joined_at' => now(),
        'role_id'   => $role->id,
    ]);

    return $post;
}
```

---

## Assertion Patterns

### Collection response
```php
->assertOk()
->assertJsonStructure(['data' => ['*' => ['id', 'title', 'status', 'created_at', 'updated_at']]])
->assertJsonCount(3, 'data')
```

### Single resource response
```php
->assertCreated()
->assertJsonStructure(['data' => ['id', 'title', 'post_id', 'status', 'created_at', 'updated_at']])
```

### Specific value
```php
->assertJsonPath('data.id', $resource->id)
->assertJsonPath('data.post_id', $post->id)
```

### DB create
```php
assertDatabaseHas('comments', ['body' => 'Hello', 'post_id' => $post->id]);
```

### DB delete
```php
assertDatabaseMissing('comments', ['id' => $comment->id]);
```

### Validation failure
```php
->assertUnprocessable()
->assertJsonValidationErrors(['body'])
```

---

## Examples

Read the example files before writing any test — they are the authoritative reference for structure, section dividers, helper naming, assertion style, and test ordering.

- `examples/post_test.php` — All Post actions in one file. Shows Tier 1 routes (no 403, with explanatory comment), Tier 2 routes (403 tests), validation for create including file/mime/size rules, file upload with `Storage::fake`, bulk delete with per-resource permission iteration, and cross-section helper reuse.
- `examples/comment_test.php` — All Comment actions in one file. Shows nested resource patterns (parent model in every route), `sometimes` validation on update, cross-parent scoping on `clone_from_id`, two helpers with different purposes (`commentPost` for Tier 1 actions, `commentPostWithPermission` for Tier 2 actions), and cross-parent isolation on bulk delete.

When in doubt about structure or ordering — match the closest example exactly.

---

## Common Mistakes to Avoid

- ❌ Using PHPUnit class syntax — always use Pest `it()` functions
- ❌ Using `beforeEach()` — models are always created locally inside each `it()` block
- ❌ Hardcoding `/api/...` URLs — always use `route('route.name', [...])`
- ❌ Manually generating auth tokens — always use `actingAs($user)`
- ❌ Combining multiple validation scenarios in one `it()` block
- ❌ Skipping the 401 unauthenticated test for any action
- ❌ Skipping the 403 unauthorized test on Tier 2 policy methods
- ❌ Omitting the Tier 1 comment when there is no 403 test — always explain why it's absent
- ❌ No DB assertion on create/update/delete happy path tests
- ❌ No `assertJsonStructure` on show happy path tests
- ❌ Using generic helper names like `createResourceWithUser()` that will collide across files — always use resource-specific names
- ❌ Forgetting `uses(RefreshDatabase::class)` — tests will bleed state into each other
- ❌ Missing `): void` on `it()` closures and helper functions
- ❌ Defining helper functions inside `it()` blocks — they must be file-scoped at the bottom of the file
