---
name: manage-service
description: Create or update Laravel service classes to match the team's strict code design pattern. Use this skill whenever you need to create a new service, add methods to an existing service, or refactor a service to follow project conventions — including BaseService extension, method signatures, PHPDoc blocks, query building, filtering, aggregation, and response helpers. Trigger this skill for any task involving Laravel service classes, even if the user just says "add a method to the service" or "create the service for this resource."
---

# Manage Service

Create or update a Laravel service class to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing the service, gather all necessary context by reading the codebase directly. Only ask if something genuinely cannot be determined from the files.

### 1. Find the controller
Read `app/Http/Controllers/{Resource}Controller.php` to get the full list of methods and their signatures. Each controller method maps to a service method with the same name. The controller's delegation call tells you exactly what parameters the service receives.

### 2. Find the model
Read `app/Models/{Resource}.php` to understand:
- Filterable columns (from `$fillable` and `$casts`) → used in `showResources` filter blocks
- Relationships → used in `with()`, `load()`, or `whereHas()` calls
- Whether the resource has a parent (e.g. `post_id`, `workspace_id`) → determines first parameter

### 3. Find the migration
Read `database/migrations/*_create_{resources}_table.php` to confirm column names, types, and any computed/nullable fields that need special handling.

### 4. Read `BaseService`
Read `app/Services/BaseService.php` to understand the available helper methods: `setQuery()`, `getOutput()`, `showResource()`, `showCreatedResource()`, `showUpdatedResource()`, `applySearchOnQuery()`, `getQuery()`, `applyDateRange()`. Use these — never reimplement them.

### 5. Look for similar services
Scan `app/Services/` for services that handle the same kind of resource (e.g. if creating `CommentService`, read `PostService` for filter/summary patterns). Match the approach of the closest existing service.

### 6. Only ask if truly blocked
If the model, migration, and controller all don't exist yet, create them and infer everything autonomously to the best of your understanding.

---

## Strict Rules

1. **Always extend `BaseService`**: `class ResourceService extends BaseService`
2. **No constructor**: Service dependencies are never injected. Other services are instantiated inline with `(new OtherService())->method(...)`.
3. **One method per controller action**: Service method names mirror controller method names exactly.
4. **Always typed**: Every method has fully typed parameters and a declared return type.
5. **PHPDoc on every method**: Description + `@param` per parameter + `@return` + `@throws` where applicable. See style below.
6. **Response helpers from BaseService**: Always use `showResource()`, `showCreatedResource()`, `showUpdatedResource()` — never return `new ResourceResource($model)` directly.
7. **Query output via `setQuery()->getOutput()`**: All collection methods end with `return $this->setQuery($query)->getOutput();`
8. **Default sort**: If a collection method has no explicit `_sort` in the request, apply a default: `if(!request()->has('_sort')) $query = $query->latest();`
9. **Imports**: `Exception` first, then Models, Enums, other Services/Facades (alphabetical within group), then Resources, then standalone utilities (Carbon, DB, etc.). No unused imports.
10. **No extra blank lines** between the opening `{` and the first method, or after the last method before `}`.

---

## Code Order (Strict)

```
1. showResources()        — GET / collection
2. showResourcesSummary() — GET /summary (if applicable)
3. createResource()       — POST /
4. named bulk actions     — POST / (e.g. publishPosts, blockCustomers)
5. deleteResources()      — DELETE / bulk
6. showResource()         — GET /{resource}
7. showResourceSummary()  — GET /{resource}/summary (if applicable)
8. updateResource()       — PUT /{resource}
9. uploadResourceFile()   — POST /{resource}/field (if applicable)
10. named member actions  — POST /{resource}/action (e.g. publishPost, blockCustomer)
11. deleteResource()      — DELETE /{resource}
```

Mirror the same top-to-bottom order as the controller.

---

## Method Body Patterns

### Show collection
```php
public function showResources(Post $post, array $data): CommentResources|array
{
    $status    = $data['status'] ?? null;
    $authorId  = $data['author_id'] ?? null;

    $query = Comment::where('post_id', $post->id);

    if (!empty($status))   $query->where('status', $status);
    if (!empty($authorId)) $query->where('created_by', $authorId);

    if (!request()->has('_sort')) $query = $query->latest();

    return $this->setQuery($query)->getOutput();
}
```

### Show collection with date range
```php
if ($dateRange) {
    $query = $this->applyDateRange($query, $dateRange, $dateRangeStart, $dateRangeEnd);
}

if (!request()->has('_sort')) $query = $query->latest();

return $this->setQuery($query)->getOutput();
```

### Show summary
```php
public function showResourcesSummary(Post $post, array $data): array
{
    // 1. Extract filters (same as showResources)
    // 2. Build and filter $query identically to showResources
    // 3. Apply date range
    $query = $this->setQuery($query)->applySearchOnQuery()->getQuery();

    // 4. Aggregate with a single selectRaw clone where possible
    $stats = (clone $query)->selectRaw('
        COUNT(*) as total,
        SUM(some_column) as some_total,
        AVG(some_column) as some_avg
    ')->first();

    // 5. Cast to correct types, compute derived metrics
    $total = (int) ($stats->total ?? 0);

    return [
        'total' => $total,
        // ...
    ];
}
```

### Show single
```php
public function showResource(Post $post, Comment $comment): CommentResource
{
    return $this->showResource($comment);
}
```

### Create
```php
public function createResource(Post $post, array $data): array
{
    $comment = Comment::create([
        ...$data,
        'post_id' => $post->id,
    ]);

    // Any related operations here (e.g. creating child records, notifications)

    return $this->showCreatedResource($comment);
}
```

### Update
```php
public function updateResource(Post $post, Comment $comment, array $data): array
{
    $comment->update($data);
    return $this->showUpdatedResource($comment);
}
```

### Delete single
```php
public function deleteResource(Post $post, Comment $comment): array
{
    $deleted = $comment->forceDelete(); // or ->delete() for soft deletes

    return [
        'deleted' => $deleted,
        'message' => $deleted ? 'Comment deleted' : 'Comment delete unsuccessful',
    ];
}
```

### Delete bulk
```php
public function deleteResources(Post $post, array $commentIds): array
{
    $comments = Comment::whereIn('id', $commentIds)->get();

    if ($total = $comments->count()) {
        foreach ($comments as $comment) {
            $this->deleteResource($post, $comment);
        }
        return ['message' => $total . ($total === 1 ? ' Comment' : ' Comments') . ' deleted'];
    }

    throw new Exception('No Comments deleted');
}
```

### Named action (e.g. block/suspend)
```php
public function suspendResource(Post $post, Comment $comment): array
{
    $alreadySuspended = $comment->suspended_at !== null;

    if (!$alreadySuspended) {
        $comment->update(['suspended_at' => now()]);
    }

    return [
        'suspended' => true,
        'message'   => $alreadySuspended ? 'Comment already suspended' : 'Comment suspended',
    ];
}
```

### Calling another service inline
```php
// Never inject — always instantiate inline
(new MediaFileService())->createMediaFile([...]);
(new NotificationService())->sendWelcomeEmail($user);
```

---

## PHPDoc Style

```php
/**
 * Short action description.
 *
 * @param Post $post
 * @param array $data
 * @return CommentResources|array
 * @throws Exception
 */
public function showComments(Post $post, array $data): CommentResources|array
```

- Always include `@throws Exception` on bulk delete, bulk action, and create methods that can throw.
- Never add `@throws` on show or update methods unless they explicitly throw.

---

## Return Type Conventions

| Method type                | Return type                    |
|----------------------------|--------------------------------|
| Show collection            | `{Resource}Resources\|array`   |
| Show single                | `{Resource}Resource`           |
| Create                     | `array`                        |
| Update                     | `array`                        |
| Delete single              | `array`                        |
| Delete bulk                | `array`                        |
| Summary (any level)        | `array`                        |
| Named action (block, etc.) | `array`                        |

---

## Examples

Read the example files before writing any service — they are the authoritative reference for filtering patterns, summary aggregation, bulk operations, and inline service calls.

- `examples/PostService.php` — Top-level resource; complex create (roles, child records, media, pivot attach); bulk delete delegating to single delete; inline calls to multiple other services
- `examples/CommentService.php` — Nested resource; create with optional content cloning; bulk delete pattern; update with conditional publish logic
- `examples/OrderService.php` — Read-only resource; rich filter block; summary with single `selectRaw` aggregation; no write methods
- `examples/OrderItemService.php` — Full example: filters, computed `addSelect` columns, bulk named actions, named member actions, member-level summary with Carbon date handling

When in doubt about a pattern — match the closest example exactly.

---

## Common Mistakes to Avoid

- ❌ Injecting other services via constructor — always use `(new OtherService())->method(...)`
- ❌ Returning `new ResourceResource($model)` directly — always use `$this->showResource($model)`
- ❌ Returning `new ResourceResources($query)` directly — always use `$this->setQuery($query)->getOutput()`
- ❌ Missing `if (!request()->has('_sort')) $query = $query->latest();` on collection queries
- ❌ Running separate queries for each summary metric — use a single `selectRaw` with multiple aggregates where possible
- ❌ Missing `@throws Exception` on bulk delete or methods that explicitly throw
- ❌ Using `$model->delete()` when the rest of the codebase uses `$model->forceDelete()` (check existing delete methods for the resource)
- ❌ Passing entire `$data` array to `Model::create()` without filtering when sensitive or unrelated keys may be present
- ❌ Missing cast to `(int)` or `(float)` on `selectRaw` aggregate results before returning them
- ❌ Imports out of order: Exception → Models → Enums → Services/Facades → Resources → Utilities
