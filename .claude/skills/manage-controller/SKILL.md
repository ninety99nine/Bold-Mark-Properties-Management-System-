---
name: manage-controller
description: Create or update Laravel API controllers to match the team's strict code design pattern. Use this skill whenever you need to create a new controller, add methods to an existing controller, or refactor a controller to follow project conventions — including constructor injection, method signatures, PHPDoc blocks, delegation to a service class, and return types. Trigger this skill for any task involving Laravel controllers, even if the user just says "add a method to the controller" or "create the controller for this resource."
---

# Manage Controller

Create or update a Laravel API controller to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing the controller, gather all necessary context by reading the codebase directly. Only ask the user if something genuinely cannot be determined from the files.

### 1. Find the route file
Look in `routes/api/` for the route file matching this resource (e.g. `blog-posts.php`). Read it to extract:
- Every route action string (e.g. `'showBlogPosts'`) → becomes a public controller method
- The route parameters in order (e.g. `{author}`, `{blog_post}`) → become typed method parameters after the Request object

### 2. Find or infer the Service class
Look in `app/Services/` for `{Resource}Service.php`. Read it to confirm the exact method names the controller should delegate to. If the service doesn't exist yet, mirror the controller method names exactly and create it.

### 3. Find existing Form Request classes
Look in `app/Http/Requests/{Resource}/` for existing request classes. Each controller method gets its own dedicated request class (e.g. `ShowPostsRequest`, `CreatePostRequest`). Note which ones already exist and create any that are missing.

### 4. Find existing Resource classes
Look in `app/Http/Resources/` for `{Resource}Resource.php` (single) and `{Resource}Resources.php` (collection). These are the return types for show-collection and show-single methods.

### 5. Only ask if truly blocked
If neither the route file nor any existing service/request classes exist, create the route file, service class, request classes, and the controller methods to the best of your understanding — accounting for both immediately necessary actions (e.g. the frontend has already implemented a call for this) and predictably necessary future actions (e.g. standard CRUD that will clearly be needed). Do not ask about method signatures, return types, or parameter order — infer from the patterns below. Only ask if you truly need to verify or confirm something.

---

## Strict Rules

1. **One controller per resource**: File lives at `app/Http/Controllers/{Resource}Controller.php`.
2. **Constructor injection**: Always inject `{Resource}Service` via the constructor. Assign to `protected $service`.
3. **No logic in controllers**: Every method body is a single `return $this->service->...()` call. The only exception is bulk ID extraction (see method body patterns below), which is two lines maximum.
4. **Dedicated Form Request per method**: Every method's first parameter is its own typed Form Request class. Never use the base `Request` class.
5. **Typed route parameters**: All route model binding parameters must be fully typed. The parameter name must exactly match the route placeholder in snake_case (e.g. route `{blog_post}` → parameter `BlogPost $blog_post`).
6. **Return types**: Always declare a return type on every method. Use union types where applicable (e.g. `PostResources|array`). See return type conventions below.
7. **PHPDoc on every method**: One-line description, then `@param` for every parameter, then `@return`. See exact style below.
8. **PHPDoc on `$service` property**: `@var` block only — no description line above it.
9. **PHPDoc on constructor**: Include `@param` for the injected service.
10. **Import order**: Models → Services → Resources → Requests (alphabetical within each group). No unused imports.
11. **No extra blank lines** between the opening `{` and the `$service` property, or after the last method before `}`.

---

## Code Order (Strict)

```
1. $service property       — @var PHPDoc
2. __construct()           — @param PHPDoc
3. Collection methods      — in the same order they appear in the route file:
   a. show{Resources}            (GET /)
   b. show{Resources}Summary     (GET /summary)
   c. create{Resource}           (POST /)
   d. named bulk actions         (POST / — e.g. publishPosts)
   e. delete{Resources}          (DELETE /)
4. Member methods          — in the same order they appear in the route file:
   a. show{Resource}             (GET /{resource})
   b. show{Resource}Summary      (GET /{resource}/summary)
   c. update{Resource}           (PUT /{resource})
   d. upload / file methods      (POST /{resource}/field)
   e. named member actions       (POST /{resource}/action — e.g. publishPost)
   f. delete{Resource}           (DELETE /{resource})
```

---

## Method Signature Patterns

### Top-level resource (no parent model)
```php
public function showPosts(ShowPostsRequest $request): PostResources|array
public function createPost(CreatePostRequest $request): array
public function deletePosts(DeletePostsRequest $request): array
public function showPost(ShowPostRequest $request, Post $post): PostResource
public function updatePost(UpdatePostRequest $request, Post $post): array
public function deletePost(DeletePostRequest $request, Post $post): array
```

### Nested resource (parent model present)
```php
public function showComments(ShowCommentsRequest $request, Post $post): CommentResources|array
public function createComment(CreateCommentRequest $request, Post $post): array
public function showComment(ShowCommentRequest $request, Post $post, Comment $comment): CommentResource
public function updateComment(UpdateCommentRequest $request, Post $post, Comment $comment): array
public function deleteComment(DeleteCommentRequest $request, Post $post, Comment $comment): array
```

---

## Return Type Conventions

| Method type                | Return type                    |
|----------------------------|--------------------------------|
| Show collection            | `{Resource}Resources\|array`   |
| Show single                | `{Resource}Resource`           |
| Create / Update / Delete   | `array`                        |
| Summary (any level)        | `array`                        |
| Named action (publish etc.)| `array`                        |
| File upload                | `array`                        |

---

## Method Body Patterns

### Standard — pass validated input to service
```php
return $this->service->methodName($parent, $request->validated());
```

### Show single — no validated data needed
```php
return $this->service->showResource($parent, $resource);
```

### Bulk action — extract IDs first, then delegate
```php
$resourceIds = $request->input('resource_ids', []);
return $this->service->deleteResources($parent, $resourceIds);
```

### File upload — use `$request->file()`, not `$request->validated()`
```php
return $this->service->uploadResourceFile($resource, $request->file('field_name'));
```

---

## PHPDoc Style

### `$service` property — `@var` only, no description
```php
/**
 * @var ResourceService
 */
protected $service;
```

### Constructor
```php
/**
 * ResourceController constructor.
 *
 * @param ResourceService $service
 */
public function __construct(ResourceService $service)
{
    $this->service = $service;
}
```

### Standard method
```php
/**
 * Show resource.
 *
 * @param ShowResourceRequest $request
 * @param Parent $parent
 * @param Resource $resource
 * @return ResourceResource
 */
public function showResource(ShowResourceRequest $request, Parent $parent, Resource $resource): ResourceResource
{
    return $this->service->showResource($parent, $resource);
}
```

---

## Examples

Read the example files before writing any controller — they are the authoritative reference for PHPDoc style, method ordering, import grouping, and delegation patterns.

- `examples/PostController.php` — Top-level resource; no parent model; file upload method (`$request->file()`); bulk delete with `$request->input()`
- `examples/CommentController.php` — Nested under a parent; standard CRUD; clean minimal reference
- `examples/OrderController.php` — Read-only resource; GET-only methods; summary route; no write methods
- `examples/OrderItemController.php` — Full example: named bulk actions (`fulfillOrderItems`), named member actions (`fulfill`, `cancel`), summary on both collection and member level

When in doubt about ordering, PHPDoc, or delegation — match the closest example exactly.

---

## Common Mistakes to Avoid

- ❌ Using `Illuminate\Http\Request` directly instead of a dedicated Form Request class
- ❌ Putting any logic in the controller beyond the single-line service delegation (or two-line bulk ID extraction)
- ❌ Missing `@param` lines in PHPDoc — there must be one per parameter, in parameter order
- ❌ Adding a description line above the `$service` property — the `@var` block has no description
- ❌ Mismatched parameter name vs. route placeholder — route `{blog_post}` → must be `BlogPost $blog_post` (snake_case)
- ❌ camelCase parameter names for route-bound models (e.g. `$blogPost` — wrong; must be `$blog_post`)
- ❌ Missing return type on any method
- ❌ Passing `$request` or `$request->all()` to the service — always use `$request->validated()`
- ❌ Using `$request->validated()` for file uploads — use `$request->file('field')`
- ❌ Member methods appearing before collection methods
- ❌ Imports out of order or grouped incorrectly (Models → Services → Resources → Requests)
