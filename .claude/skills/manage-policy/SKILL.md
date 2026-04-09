---
name: manage-policy
description: Create or update Laravel Policy classes to match the team's strict code design pattern. Use this skill whenever you need to create a new policy, add methods to an existing policy, or refactor a policy to follow project conventions — including BasePolicy extension, before() pattern, method signatures, PHPDoc, permission check tiers, and the correct relationship between nesting level and method parameters. Trigger this skill for any task involving Laravel policies, even if the user just says "add a policy method" or "create the policy for this resource."
---

# Manage Policy

Create or update a Laravel Policy class to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing the policy, gather context by reading the codebase directly.

### 1. Find the Form Requests for this resource
Read `app/Http/Requests/{Resource}/` to see what `authorize()` calls are being made. Each `$this->user()->can('ability', [...])` call maps to a policy method with a matching signature.

### 2. Determine the nesting level
Read the route file (`routes/api/{resource}.php`) to identify the resource's parent chain. The nesting level determines the method parameter chain — every ancestor model appears in every method signature.

### 3. Find the permission names
Search `app/Enums/` for a Permission enum (e.g. `Permission.php`, `AppPermission.php`). Use existing permission strings — do not invent new ones. If no enum exists, infer permission names from the pattern `{resource}.{action}` (e.g. `post.update`, `post.delete`).

### 4. Read `BasePolicy` and the auth/permission service
Read `app/Policies/BasePolicy.php` and any service in `app/Services/` that handles authorization (e.g. `AuthService.php`) to understand available helpers such as `hasPermission()`, `isSuperAdmin()`, `isMember()`. Adapt all permission checks to the helpers available in the project.

### 5. Only ask if truly blocked
If the route file, controller, and form requests do not exist, create them to the best of your understanding. Infer fields from the migration, model, or controller request classes if any exist. Only ask if you truly need to verify or confirm something.

---

## Strict Rules

1. **Always extend `BasePolicy`** — never extend nothing or inject the constructor manually.
2. **`before()` always uses explicit ternary**: `return $this->authService->isSuperAdmin($user) ? true : null;` — never `?: null`.
3. **PHPDoc on every method** — including `before()`. Use the correct `@param` names that match the actual parameters (never copy-paste the wrong model name).
4. **Method order**: `before()` first, then `viewAny`, `view`, `create`, `update`, `deleteAny`, `delete`, then named actions (`publish`, `block`, `unblock`, etc.).
5. **Method signatures follow the parent chain** — every ancestor model is included in every method. See nesting rules below.
6. **`deleteAny` at Level 0 iterates over request IDs** — this only applies when the resource being deleted *is* the top-level scope resource and each ID *is* that resource's ID. Level 1+ policies use the parent model directly (see `deleteAny` pattern below).
7. **Permission checks always receive the correct scope ID** — the scope resource (e.g. the workspace, organisation, or app that owns the data) is always the permission scope. Never pass a child resource ID where a parent scope ID is expected.
8. **Imports**: Models in alphabetical order. `AuthService` is inherited via `BasePolicy` — do not re-import it.
9. **No unused imports.**
10. **`before()` PHPDoc description** must always say: `"Grant all permissions to super admins."` — consistent across all policies.

---

## Method Signature Rules by Nesting Level

The policy method receives `User $user` first, then every parent model in chain order, then the resource model itself (for member methods).

### Level 0 — Top-level resource (e.g. `Post`, `Workspace`)
```php
// Collection methods
public function viewAny(User $user): bool
public function create(User $user): bool
public function deleteAny(User $user): bool

// Member methods
public function view(User $user, Post $post): bool
public function update(User $user, Post $post): bool
public function delete(User $user, Post $post): bool
```

### Level 1 — Nested under a parent (e.g. `Comment` under `Post`, `OrderItem` under `Order`)
```php
// Collection methods
public function viewAny(User $user, Post $post): bool
public function create(User $user, Post $post): bool
public function deleteAny(User $user, Post $post): bool

// Member methods
public function view(User $user, Post $post, Comment $comment): bool
public function update(User $user, Post $post, Comment $comment): bool
public function delete(User $user, Post $post, Comment $comment): bool
```

### Level 2 — Nested under parent + intermediate (e.g. `Reply` under `Comment` under `Post`)
```php
// Collection methods
public function viewAny(User $user, Post $post, Comment $comment): bool

// Member methods
public function view(User $user, Post $post, Comment $comment, Reply $reply): bool
public function update(User $user, Post $post, Comment $comment, Reply $reply): bool
```

---

## Permission Check Tiers

Use the right tier for each action based on how sensitive it is:

### Tier 1 — Open to any authenticated member
Used for: `viewAny`, `view`, and `create` on non-sensitive resources. Membership/scoping middleware already verified access at the route level.
```php
public function viewAny(User $user, Post $post): bool
{
    return true;
}
```

### Tier 2 — Requires a named permission
Used for: `update`, `delete`, `deleteAny`, and named actions on sensitive resources. Use the project's permission helper with the correct permission string.
```php
public function update(User $user, Post $post, Comment $comment): bool
{
    return $this->authService->hasPermission($user, 'comment.update', $post->id);
}
```

### Tier 3 — Ownership or special logic
Used for: actions where a user can act on their own resource regardless of role.
```php
public function delete(User $user, Post $post, Comment $comment): bool
{
    // Authors can always delete their own comments
    if ($comment->created_by === $user->id) {
        return true;
    }
    return $this->authService->hasPermission($user, 'comment.delete', $post->id);
}
```

---

## `deleteAny` Pattern

The permission helper always expects the **scope resource ID** as its third argument — never a child resource ID. The scope resource is whatever parent entity owns the data (e.g. the workspace, the post, the organisation).

### Level 0 — top-level resource (the resource being deleted IS the scope)

At Level 0, the resource being bulk-deleted is the scope itself (e.g. deleting workspaces). There is no parent model in scope, so we read the IDs from the request and verify permission within each individually:

```php
public function deleteAny(User $user): bool
{
    $resourceIds = request()->input('workspace_ids', []);
    foreach ($resourceIds as $resourceId) {
        if (!$this->authService->hasPermission($user, 'workspace.delete', $resourceId)) {
            return false;
        }
    }
    return !empty($resourceIds);
}
```

This is the **only** case where `deleteAny` iterates — because the resource being deleted and the permission scope are the same thing.

### Level 1+ — nested resource (parent is already in scope)

For any resource nested under a parent, the parent model is already in the method signature. A single permission check against the parent's ID is all that's needed — all items being bulk-deleted belong to this one parent:

```php
public function deleteAny(User $user, Post $post): bool
{
    return $this->authService->hasPermission($user, 'comment.delete', $post->id);
}
```

### Open resource — any member can bulk-delete

```php
public function deleteAny(User $user, Post $post): bool
{
    return true;
}
```

> **Never** pass a child resource ID where the scope ID is expected. If you find yourself wanting to do this, use the parent model from the signature instead.

---

## Named Action Methods

Named actions (e.g. `publish`, `block`, `unblock`, `fulfill`) follow the same nesting signature rules as `delete`/`deleteAny`:

```php
/**
 * Determine whether the user can publish any posts.
 *
 * @param User $user
 * @return bool
 */
public function publishAny(User $user): bool
{
    return $this->authService->hasPermission($user, 'post.publish', $user->workspace_id);
}

/**
 * Determine whether the user can publish the post.
 *
 * @param User $user
 * @param Post $post
 * @return bool
 */
public function publish(User $user, Post $post): bool
{
    return $this->authService->hasPermission($user, 'post.publish', $post->workspace_id);
}
```

---

## PHPDoc Style

```php
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
 * Determine whether the user can view any {resources}.
 *
 * @param User $user
 * @param Post $post
 * @return bool
 */
public function viewAny(User $user, Post $post): bool
{
    return true;
}
```

- `before()` description is always: `"Grant all permissions to super admins."`
- `viewAny` description: `"Determine whether the user can view any {resources}."`
- `view` description: `"Determine whether the user can view the {resource}."`
- `create` description: `"Determine whether the user can create {resources}."`
- `update` description: `"Determine whether the user can update the {resource}."`
- `deleteAny` description: `"Determine whether the user can delete any {resources}."`
- `delete` description: `"Determine whether the user can delete the {resource}."`

---

## Examples

Read the example files before writing any policy.

- `examples/PostPolicy.php` — **Level 0** (top-level). Shows `deleteAny` iterating over request IDs. Shows Tier 2 permission checks on `update`/`delete`. Shows `viewAny`/`view`/`create` as Tier 1 (open).
- `examples/CommentPolicy.php` — **Level 1** (nested under Post). Shows all six standard CRUD methods with `Post $post` in every signature. Shows Tier 2 on `update`/`delete`/`deleteAny`. Shows Tier 3 ownership check on `delete`.
- `examples/OrderItemPolicy.php` — **Level 1** (nested under Order, sensitive resource). Shows a resource where `create` is also Tier 2. Good reference for when to escalate `create` beyond Tier 1.

---

## Common Mistakes to Avoid

- ❌ Using `?: null` in `before()` instead of `? true : null`
- ❌ Not extending `BasePolicy` (injecting the auth service manually in the constructor)
- ❌ Wrong `@param` name in PHPDoc (copy-pasting `$post` when the parameter is `$comment`)
- ❌ Missing parent models in method signatures (e.g. Level 1 policy method missing `Post $post`)
- ❌ `deleteAny` at Level 0 not iterating over request IDs — without a parent model in scope, each ID must be checked individually
- ❌ Passing a child resource ID (comment ID, order item ID, etc.) to the permission helper — always use the parent scope ID
- ❌ Missing PHPDoc on any method
- ❌ Unused imports
- ❌ Returning `false` (not `null`) from `before()` for non-super-admins — `null` means "proceed to method check", `false` means "deny all"
