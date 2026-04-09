---
name: manage-api-resource
description: Create or update Laravel API resource classes to match the team's strict code design pattern. Use this skill whenever you need to create a new resource or collection class, add fields to an existing resource, or refactor a resource to follow project conventions — including field ordering, relationship rendering, counts, computed fields, nullability handling, and section comments. Trigger this skill for any task involving Laravel API resources, even if the user just says "add a field to the resource" or "create the resource for this model."
---

# Manage API Resource

Create or update a Laravel API resource class to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing the resource, gather all necessary context by reading the codebase directly. Only ask if something genuinely cannot be determined from the files.

### 1. Find the model
Read `app/Models/{Resource}.php` to extract:
- All `$fillable` and `$casts` columns → become scalar fields in `toArray()`
- All relationship methods → each one that may be loaded becomes a `whenLoaded()` entry
- All relationship methods that return collections → use `::collection()`; singular → use `::make()`

### 2. Check the base resource
Look for a `BaseResource` (or similarly named base class) in `app/Http/Resources/`. Read it to confirm available helper methods. Always extend it instead of `JsonResource` directly. If no base class exists in the project, extend `JsonResource` and note this deviation.

### 3. Look at related resources
Scan `app/Http/Resources/` for existing resource classes referenced by this model's relationships. Use them as the rendering class in `whenLoaded()` calls (e.g. `AuthorResource::make(...)`, `CommentResource::collection(...)`).

### 4. Check the migration
Read `database/migrations/*_create_{resources}_table.php` to confirm which timestamp columns are nullable (use `?->`) vs. guaranteed (use `->`). Also note any computed or virtual columns.

### 5. Check for an auth/permission service
Search `app/Services/` for any class that performs authorization checks (e.g. `AuthService`, `PermissionService`, `PolicyService`). If found, use `resolve(ServiceClass::class)` inline for permission fields — never inject via constructor.

### 6. Only ask if truly blocked
If either the migration file or model does not exist, create them to the best of your understanding. Infer fields from any existing controller request classes (`app/Http/Requests/`). Only ask the user if you cannot determine something after exhausting the codebase.

---

## Strict Rules

1. **Always extend the project's base resource**: Never extend `JsonResource` directly unless no base class exists in the project.
2. **Always create both files**: Every resource needs a singular `{Resource}Resource.php` AND a collection `{Resource}Resources.php`.
3. **`toArray()` signature**: Always `public function toArray($request): array` — the `$request` parameter is **not** type-hinted in the method signature (PHPDoc carries the type).
4. **PHPDoc on `toArray()`**: Always include `@param Request $request` and `@return array`. Import `Illuminate\Http\Request`.
5. **Field order**: Follow the strict section order below — no deviations.
6. **Nullable timestamps**: Use `$this->created_at?->toDateTimeString()` for nullable columns, `->toDateTimeString()` for guaranteed (verify in the migration).
7. **Relationships use `whenLoaded()`**: Never access `$this->relation` directly for relationships — always `$this->whenLoaded('methodName')`.
8. **ALL output keys must be snake_case**: Every key in the returned array must use snake_case — including relationship keys, count keys, and computed fields. Never use camelCase as an array key. The argument passed to `whenLoaded()` and `whenCounted()` must still match the camelCase model method name, but the array key is always snake_case (e.g., `'charge_type' => ...$this->whenLoaded('chargeType')`, `'cashbook_entries_count' => $this->whenCounted('cashbookEntries')`).
9. **Counts use `whenCounted()`**: Always `$this->whenCounted('relationMethodName')` — the argument is camelCase matching the model method name, but the output key is snake_case (e.g., `'unit_tenants_count' => $this->whenCounted('unitTenants')`).
10. **No logic outside `toArray()`**: Never inject services via the constructor. Use `resolve(ServiceClass::class)` inline for permission checks.
11. **Collection class is minimal**: Only the `$collects` property — no constructor, no methods, no PHPDoc.
12. **Imports**: `Illuminate\Http\Request` always. Add other Resource classes and Services as needed. No unused imports.

---

## Field Order in `toArray()` (Strict)

```
1. Scalar fields          — id first, then all model columns in a logical grouping
2. Timestamps             — created_at, updated_at (always last among scalars)
3. [blank line]
4. Computed fields        — derived/calculated values (rates, statuses, derived booleans)
5. [blank line + comment] // Countable attributes
6. whenCounted entries    — $this->whenCounted('relationName')
7. [blank line + comment] // Relationships
8. whenLoaded entries     — single: ::make(), collection: ::collection()
9. [blank line + comment] // Pivot   (only if pivot data exists)
10. Pivot fields
11. [blank line + comment] // Permissions   (only if permission checks exist)
12. Permission fields     — can_* keys using resolve(AuthService::class) or equivalent
```

Omit any section entirely (including its comment) if the resource has no entries for it.

---

## Rendering Patterns

### Single relationship
```php
'author' => AuthorResource::make($this->whenLoaded('author')),
```

### Collection relationship (no fallback needed — whenLoaded returns MissingValue, not null)
```php
'comments' => CommentResource::collection($this->whenLoaded('comments')),
```

### Collection relationship loaded at root level (may need empty fallback)
```php
'tags' => TagResource::collection($this->whenLoaded('tags') ?? []),
```

### Inline partial relationship (expose limited fields only — e.g. for security or to avoid circular nesting)
```php
'created_by' => $this->whenLoaded('createdBy', fn() => [
    'id'         => $this->createdBy->id,
    'name'       => $this->createdBy->name,
    'first_name' => $this->createdBy->first_name,
]),
```

### Pivot
```php
'post_user' => $this->post_user ? PostUserResource::make($this->post_user) : null,
```

### Count
```php
'comments_count' => $this->whenCounted('comments'),
```

### Permission check
```php
'can_delete_post' => $request->user()
    ? resolve(AuthService::class)->hasPermission($request->user(), 'post.delete', $this->id)
    : false,
```

---

## Nullability Reference

| Situation | Syntax |
|---|---|
| Nullable timestamp column | `$this->created_at?->toDateTimeString()` |
| Non-nullable timestamp column | `$this->created_at->toDateTimeString()` |
| Nullable scalar field | `$this->field ?? null` |
| Integer boolean (0/1) coerced to bool | `$this->field ? true : false` |
| Nullable boolean check | `!is_null($this->blocked_at)` |
| Computed rate (guard division by zero) | `$this->total > 0 ? round(($this->x / $this->total) * 100, 1) : 0` |

---

## Collection Class Template

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class {Resource}Resources extends ResourceCollection
{
    public $collects = {Resource}Resource::class;
}
```

No PHPDoc, no constructor, no extra methods. That is the entire file.

---

## Examples

Read the example files below before writing any resource — they are the authoritative reference for field ordering, section comments, and rendering patterns.

- `examples/PostResource.php` — Full-featured: scalars, counts, relationships, pivot, permissions, inline `resolve()`
- `examples/CommentResource.php` — Column-aligned scalars; inline partial `whenLoaded()` with closure; mixed single/collection relationships
- `examples/OrderResource.php` — Dense scalar block; helper method from BaseResource; non-nullable timestamps (no `?->`)
- `examples/CustomerResource.php` — Computed rates/percentages inline; `!is_null()` boolean pattern; addSelect computed columns passed through

When in doubt about field order or rendering — match the closest example exactly.

---

## Common Mistakes to Avoid

- ❌ Extending `JsonResource` instead of the project's `BaseResource`
- ❌ Type-hinting `$request` in the method signature: `toArray(Request $request)` — wrong; must be `toArray($request)`
- ❌ Accessing relationships directly (`$this->comments`) instead of `$this->whenLoaded('comments')`
- ❌ Using camelCase for output array keys — all keys must be snake_case. Wrong: `'chargeType'`, `'billedToOwner'`, `'cashbookEntries_count'`. Right: `'charge_type'`, `'billed_to_owner'`, `'cashbook_entries_count'`.
- ❌ Using `$this->whenCounted('snake_case')` — the argument to `whenCounted()` must be camelCase matching the model method (`whenCounted('unitTenants')` not `whenCounted('unit_tenants')`), but the output KEY is snake_case (`'unit_tenants_count'`)
- ❌ Using `->toDateTimeString()` on a nullable timestamp without the null-safe operator
- ❌ Forgetting the collection fallback `?? []` on relationships that are eager-loaded at the root level
- ❌ Injecting services in the constructor — use `resolve(ServiceClass::class)` inline
- ❌ Adding PHPDoc or extra methods to the collection class
- ❌ Forgetting to create the `{Resource}Resources.php` collection class alongside the singular resource
- ❌ Section comments without a blank line above them
