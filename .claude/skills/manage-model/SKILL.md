---
name: manage-model
description: Create or update Laravel Eloquent models to match the team's strict code design pattern. Use this skill whenever you need to create a new Laravel model file, update or refactor an existing model, or ensure a model follows the project's conventions — including traits, casts, fillable, scopes, relationships, and accessors. Trigger this skill for any task involving Laravel models, even if the user just says "add a relationship to the model" or "create the model for this migration."
---

# Manage Model

Create or update a Laravel Eloquent model to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing the model, gather all necessary context by reading the codebase directly. Only ask the user if something genuinely cannot be determined from the files.

### 1. Find the migration file
Look in `database/migrations/` for a filename matching the model (e.g. `*_create_posts_table.php`). Read it to extract:
- All column names, types, and modifiers (`nullable`, `default`, `unique`)
- Whether the primary key is `uuid()`/`ulid()` → use `HasUuids`
- Foreign key columns → each one implies a `belongsTo` relationship
- Pivot tables (e.g. `*_create_post_tag_table.php`) → imply `belongsToMany` on both sides
- If the migration file does not exist, create one

### 2. Discover related models
Scan `app/Models/` to find models that match the foreign keys found in step 1. Read those model files briefly to confirm the inverse relationship method name if it already exists.

### 3. Determine search scope
- **Include** a search scope if the model has a human-readable identifier column (`name`, `title`, `email`, `slug`, `code`, etc.)
- **Omit** if the model is clearly a pivot or pure lookup table (no meaningful searchable column, name ends in a conjunction like `PostTag`, `RolePermission`, etc.)
- Do **not** ask the user — make the call based on the schema.

### 4. Only ask if truly blocked
If the migration file does not exist yet and has not been provided, create one with the appropriate columns, default values, foreign key constraints, and indexes based on what the model must hold. Do not ask about relationships, casts, or scope — infer all of these from the schema. Only ask if you truly need to verify or confirm something.

---

## Strict Rules

1. **Traits**: Always include `HasFactory`. Add `HasUuids` if the migration uses `uuid` or `ulid` as the primary key type.
2. **`$casts`**: Always declare, even if empty (`protected $casts = [];`). Cast every column to its correct PHP type: `'string'`, `'integer'`, `'float'`, `'boolean'`, `'array'`, `'datetime'`, etc. Enum columns cast to their Enum class.
3. **`$fillable`**: List every column that is mass-assignable. Exclude `id`, `created_at`, `updated_at`, and `deleted_at`.
4. **Search scope**: Include a `search` scope on all primary models (not pivot/lookup tables). Search on the most human-readable column (e.g. `name`, `title`, `email`). Use the `#[Scope]` attribute syntax.
5. **Relationships**: Include every relationship implied by the migration (foreign keys → `belongsTo`, inverse → `hasMany`/`hasOne`, pivot tables → `belongsToMany`). Use the correct return type hints.
6. **Comments**: Every property and method must have a PHPDoc block. See examples for exact comment style.
7. **Imports**: Import every class used. Never use unqualified class names.
8. **No extra blank lines** between the opening `{` and the first property, or between the last method and closing `}`.

---

## Code Order (Strict)

Write code in this exact order inside the class body:

```
1. Traits (use HasFactory, HasUuids;)
2. $casts        — always present, with PHPDoc
3. $fillable     — always present, with PHPDoc
4. search scope  — if applicable, with PHPDoc
5. other scopes  — if any, with PHPDoc
6. relationships — in this sub-order: belongsTo first, then hasOne, hasMany, morphOne, morphMany, belongsToMany
7. accessors     — if any, with PHPDoc
```

---

## Comment Style

Follow this exact PHPDoc style — no deviations:

```php
/**
 * Short description.
 *
 * @var array          ← for properties
 * @return ReturnType  ← for methods
 */
```

For scopes:

```php
/**
 * Scope a query by search term.
 *
 * @param Builder $query
 * @param string $searchTerm
 * @return void
 */
#[Scope]
protected function search(Builder $query, string $searchTerm): void
```

---

## Common Cast Types Reference

| Column type          | Cast value        |
|----------------------|-------------------|
| VARCHAR / TEXT       | `'string'`        |
| INT / BIGINT         | `'integer'`       |
| DECIMAL / FLOAT      | `'float'`         |
| BOOLEAN / TINYINT(1) | `'boolean'`       |
| JSON                 | `'array'`         |
| TIMESTAMP / DATETIME | `'datetime'`      |
| ENUM (PHP Enum)      | `MyEnum::class`   |

---

## Examples

Read the example files before writing any model — they are the authoritative reference for syntax, spacing, and comment style.

- `examples/Post.php` — Full-featured model: UUIDs, casts, fillable, search scope, morphOne, morphMany, hasMany, belongsToMany
- `examples/Comment.php` — Simpler model: UUIDs, empty casts, fillable, search scope, belongsTo, hasOne, hasMany

When in doubt about formatting, spacing, or commenting — copy the style from these files exactly.

---

## Common Mistakes to Avoid

- ❌ Missing `#[Scope]` attribute on scope methods
- ❌ Forgetting to import `Builder` when using a scope
- ❌ Omitting `$casts` entirely (it must always be present)
- ❌ Including `id`, `created_at`, `updated_at` in `$fillable`
- ❌ Missing return type hints on relationship methods
- ❌ Relationships without PHPDoc blocks
- ❌ Using `belongsToMany` without importing it (note: `BelongsToMany` capital B)
- ❌ Adding a search scope to pivot/lookup-only models
