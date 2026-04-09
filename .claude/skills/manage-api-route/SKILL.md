---
name: manage-api-route
description: Create or update Laravel API route files to match the team's strict code design pattern. Use this skill whenever you need to create a new API route file, add routes to an existing file, or refactor routes to follow project conventions — including prefix structure, middleware, controller binding, route model binding, and naming. Trigger this skill for any task involving Laravel API routes, even if the user just says "add a route for X" or "create the routes for this controller."
---

# Manage API Route

Create or update a Laravel API route file to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing the route file, gather all necessary context by reading the codebase directly. Only ask the user if something genuinely cannot be determined from the files.

### 1. Find the controller
Look in `app/Http/Controllers/` for the controller that will handle these routes. Read it to extract:
- All public method names → these become the route action strings (e.g. `'showPosts'`)
- The resource the controller manages → determines the route prefix and naming convention
- If the controller does not exist, create one with methods inferred from context

### 2. Determine the route prefix and nesting
- Check `routes/api/` for existing route files to understand the nesting convention used in the project
- If the resource belongs to a parent (has a `{parent}_id` foreign key), nest under `{parents}/{parent}/`
- Top-level resources use a simple prefix with no parent segment

### 3. Identify middleware
- Determine the project's primary authentication middleware by checking existing route files (e.g. `auth:sanctum`, `auth:api`) — always apply it
- If routes are scoped under a parent resource, check whether a scoping/permission middleware is consistently applied (e.g. `app.permission`, `team.member`) — apply it if so
- Any additional per-route middleware (e.g. activity tracking, last-seen updates) should come from context or the controller — do not guess

### 4. Determine route model binding
- If the route has a `{resource}` parameter, add the comment `// Explicit route model binding applied: AppServiceProvider.php` above that nested group
- Check `app/Providers/AppServiceProvider.php` to confirm the binding exists; if not, add it and note that it has been added

### 5. Determine how route files are loaded
- Check `routes/api.php` (or the equivalent bootstrap file) for how sub-files in `routes/api/` are loaded — e.g. a `glob()` loop, explicit `require`, or `Route::apiRoutes()`
- Follow whatever pattern is already established; if none exists, use a `glob()` loop and note it has been added

### 6. Only ask if truly blocked
If the controller does not exist and methods are unknown, create the controller with methods inferred to the best of your understanding. Do not ask about prefix, middleware, or naming — infer from the codebase. Only ask if you genuinely cannot determine something.

---

## Strict Rules

1. **One file per resource**: Each resource gets its own file in `routes/api/`. File name is the kebab-case plural resource name (e.g. `blog-posts.php`).
2. **Route prefix**: Always use `Route::prefix()` as the outermost chain — never register routes outside a prefix group.
3. **Controller binding**: Always use `->controller(XxxController::class)` on the outer group — never pass `[Controller::class, 'method']` arrays to individual routes.
4. **Middleware**: Chain `->middleware([...])` on the outer group. List middleware as an array even for a single value.
5. **Route actions**: Use string shorthand `'methodName'` (not array syntax) because the controller is already bound on the group.
6. **Route names**: Use dot-notation, all lowercase, matching the HTTP verb + resource name. See naming conventions below.
7. **Route model binding group**: Wrap `{resource}` routes in a `Route::prefix('{resource}')->group(function () { ... })` sub-group. Always add the model binding comment above it.
8. **Imports**: Only import the `Route` facade and the single controller used in the file. No unused imports.
9. **No `->name()` on groups**: Names go on individual routes only.
10. **Trailing comma style**: No trailing commas inside `->middleware([...])` arrays.

---

## Route Naming Conventions

Names use dot-notation: `{verb}.{singular-resource}` or `{verb}.{singular-resource}.{sub-action}`.

| HTTP Method | Action pattern         | Name example                    |
|-------------|------------------------|---------------------------------|
| GET         | list all               | `show.blog.posts`               |
| GET         | single resource        | `show.blog.post`                |
| GET         | sub-action             | `show.blog.post.summary`        |
| POST        | create                 | `create.blog.post`              |
| POST        | named action           | `publish.blog.post`             |
| PUT         | full update            | `update.blog.post`              |
| PATCH       | partial update         | `update.blog.post`              |
| DELETE      | delete all (bulk)      | `delete.blog.posts`             |
| DELETE      | delete single          | `delete.blog.post`              |

Words in names are separated by dots, not hyphens (e.g. `show.order.items`, not `show.order-items`).

---

## File Structure Template

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{ResourceController};

Route::prefix('{prefix}')
    ->controller({ResourceController}::class)
    ->middleware(['auth:sanctum', ...])
    ->group(function () {
        // Collection routes
        Route::get('/', 'showResources')->name('show.resources');
        Route::post('/', 'createResource')->name('create.resource');
        Route::delete('/', 'deleteResources')->name('delete.resources');

        // Extra collection-level routes (e.g. summaries) go here, before the model binding group

        // Explicit route model binding applied: AppServiceProvider.php
        Route::prefix('{resource}')->group(function () {
            Route::get('/', 'showResource')->name('show.resource');
            Route::put('/', 'updateResource')->name('update.resource');
            Route::delete('/', 'deleteResource')->name('delete.resource');

            // Extra member-level routes go here
        });
    });
```

---

## Examples

Read the example files before writing any route file — they are the authoritative reference for structure, ordering, and naming.

- `examples/posts.php` — Top-level resource; per-resource middleware on the model binding group; per-route middleware for activity tracking
- `examples/comments.php` — Nested under a parent resource; standard CRUD pattern
- `examples/orders.php` — Nested resource; GET-only with a summary sub-route; no write routes on collection
- `examples/order-items.php` — Nested resource; mixed verbs; multiple named action routes (e.g. `fulfill`, `cancel`); summary on both collection and member
- `examples/invoices.php` — Nested resource; clean standard CRUD; good minimal reference

When in doubt about ordering or naming — match the closest example exactly.

---

## API Routes Loading

API routes defined inside `routes/api/` are loaded into `routes/api.php` via the following pattern (adapt to whatever already exists in the project):

```php
// In routes/api.php

foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
    require $routeFile;
}
```

---

## Common Mistakes to Avoid

- ❌ Registering routes outside of a `Route::prefix()->group()` wrapper
- ❌ Using array syntax `[Controller::class, 'method']` on individual routes instead of string shorthand
- ❌ Applying `->middleware()` on individual routes instead of the outer group (unless intentionally route-specific, like an activity-tracking middleware)
- ❌ Using hyphens in route names (use dots: `show.order.items` not `show.order-items`)
- ❌ Forgetting the model binding comment above the `{resource}` prefix group
- ❌ Using a plural resource name in single-resource routes (e.g. `show.posts` for a single post — should be `show.post`)
- ❌ Importing unused classes or missing the controller import
