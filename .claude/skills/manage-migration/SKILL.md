---
name: manage-migration
description: Create or update Laravel database migration files to match the team's strict code design pattern. Use this skill whenever you need to create a new migration, add or modify columns on an existing table, or refactor a migration to follow project conventions — including column ordering, foreign key declarations, index placement, enum usage, and PHPDoc. Trigger this skill for any task involving Laravel migrations, even if the user just says "add a column to the table" or "create the migration for this model."
---

# Manage Migration

Create or update a Laravel database migration to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing the migration, gather all necessary context by reading the codebase directly. Only ask if something genuinely cannot be determined from the files.

### 1. Find the model
Read `app/Models/{Resource}.php` to extract:
- `$fillable` → the columns this table must have
- `$casts` → informs column types (e.g. `'boolean'` → `$table->boolean()`, `'array'` → `$table->json()`)
- Relationship methods → each `belongsTo` implies a `foreignUuid` column; `belongsToMany` implies a pivot table migration

### 2. Scan for related Enum classes
Search `app/Enums/` for Enums referenced in `$casts` or the model. For each Enum column, use `$table->enum('col', EnumClass::values())->default(EnumClass::DEFAULT->value)`.

### 3. Look at existing migrations
Scan `database/migrations/` to understand the table naming convention and to confirm parent table names for `->constrained('parent_table')`.

### 4. Infer indexes
Look at the service class (`app/Services/{Resource}Service.php`) for filter blocks — every column filtered with `->where('column', ...)` in `show{Resources}()` is a strong candidate for an index.

### 5. Only ask if truly blocked
If the model does not exist, create the migration file and model to the best of your understanding. Infer columns from the controller's Form Request classes in `app/Http/Requests/{Resource}/`. Only ask if you truly need to verify or confirm something.

---

## Strict Rules

1. **Always `return new class extends Migration`** — never a named class.
2. **PHPDoc on both `up()` and `down()`** — always, no exceptions.
3. **UUID primary key**: Always `$table->uuid('id')->primary()` as the very first column.
4. **Foreign keys use `foreignUuid`**: Always `$table->foreignUuid('col_id')->constrained('table')->cascadeOnDelete()` (or `nullOnDelete()` for nullable FKs).
5. **`timestamps()` goes after foreign keys, before indexes.**
6. **Indexes go last** — after `timestamps()`, each on its own line.
7. **Enums use the Enum class**: `$table->enum('col', EnumClass::values())->default(EnumClass::VALUE->value)` — never hardcoded string arrays.
8. **Counter columns**: `$table->unsignedInteger('col')->default(0)` — never `integer()` for counts or durations.
9. **Import order**: Enum classes first (alphabetical), then `Migration`, `Blueprint`, `Schema`.
10. **Blank lines**: Add a blank line after the opening `{` on tables with more than ~5 columns. Separate logical column groups with blank lines.

---

## Column Order (Strict)

```
1. $table->uuid('id')->primary()
2. Core identifier/string columns       (name, title, slug, phone, etc.)
3. Status/enum columns
4. Boolean flags                        (non-nullable: active, verified, etc.)
5. Counter & duration columns           (unsignedInteger with default(0))
6. Nullable timestamp columns           (suspended_at, verified_at, etc.)
7. JSON columns
8. Foreign key columns                  (foreignUuid)
9. $table->timestamps()
10. Indexes                             ($table->index('col'))
```

---

## Foreign Key Patterns

### Required parent (non-nullable) — cascade on delete
```php
$table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
```

### Optional parent (nullable) — null on delete
```php
$table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
```

### Self-referential or cross-table optional
```php
$table->foreignUuid('parent_id')->nullable()->constrained('comments')->nullOnDelete();
```

---

## Column Type Reference

| Data | Column method |
|---|---|
| UUID primary key | `uuid('id')->primary()` |
| Short fixed-length string | `string('col', N)` — e.g. `string('country', 2)` |
| Standard string (VARCHAR 255) | `string('col')` |
| Long text | `text('col')` |
| Enum (PHP Enum class) | `enum('col', EnumClass::values())->default(EnumClass::X->value)` |
| Boolean (non-nullable) | `boolean('col')` |
| Counter / duration | `unsignedInteger('col')->default(0)` |
| Nullable event timestamp | `timestamp('col')->nullable()` |
| JSON / array | `json('col')->nullable()` |
| Foreign UUID | `foreignUuid('col_id')->constrained('table')->cascadeOnDelete()` |

---

## Index Guidelines

Add a `$table->index('col')` for each column that is:
- Filtered with `WHERE` in service query methods (`showResources`, `showSummary`)
- Sorted or grouped on (`ORDER BY`, `GROUP BY`)
- Used in a `whereColumn()` subquery

Do **not** index: `id` (already primary), simple boolean flags (low cardinality), `timestamps` columns unless queried directly.

---

## Blank Line Convention

- **Simple tables** (≤ 5 columns, no FKs): no blank line after opening `{`, no internal blank lines.
- **Complex tables** (> 5 columns or multiple logical groups): blank line after opening `{`, blank lines between each logical group.

---

## Examples

Read the example files before writing any migration — they are the authoritative reference for column ordering, blank lines, foreign key syntax, and index placement.

- `examples/create_posts_table.php` — Simple table: enum column with Enum class, single index, no foreign keys
- `examples/create_comments_table.php` — Medium table: nullable FK with `nullOnDelete()`, required FK with `cascadeOnDelete()`
- `examples/create_customers_table.php` — Complex table: logical column groups, counters, nullable timestamp, multiple indexes
- `examples/create_orders_table.php` — Dense table: string length constraints, json column, multiple FKs, many indexes

When in doubt about grouping, spacing, or index decisions — match the closest example exactly.

---

## Common Mistakes to Avoid

- ❌ Using a named class (`class CreatePostsTable extends Migration`) instead of `return new class extends Migration`
- ❌ Missing PHPDoc on `up()` or `down()`
- ❌ Using `$table->increments()` or `$table->id()` — always use `uuid('id')->primary()`
- ❌ Using `integer()` for counters — always `unsignedInteger()->default(0)`
- ❌ Hardcoding enum values as arrays — always use `EnumClass::values()`
- ❌ Putting indexes before `timestamps()`
- ❌ Putting `timestamps()` before foreign keys (foreign keys come first)
- ❌ Missing `->nullable()` on a foreign key column that can be null
- ❌ Using `->onDelete('cascade')` string form — always use `->cascadeOnDelete()` or `->nullOnDelete()`
- ❌ Forgetting to import Enum classes used in `enum()` column definitions
- ❌ Not specifying string length for constrained-length fields (phone numbers, country codes, short codes, external IDs, etc.)
