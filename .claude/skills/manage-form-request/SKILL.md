---
name: manage-form-request
description: Create or update Laravel Form Request classes to match the team's strict code design pattern. Use this skill whenever you need to create a new Form Request, add validation rules to an existing request, or refactor requests to follow project conventions — including authorize() policy calls, rules(), messages(), import hygiene, and the create vs update rule distinction. Trigger this skill for any task involving Laravel Form Requests, even if the user just says "add validation for this field" or "create the requests for this controller."
---

# Manage Form Request

Create or update a Laravel Form Request class to strictly match the team's code design pattern.

## Context Gathering (Autonomous — Do Not Ask the User)

Before writing any request class, gather context by reading the codebase directly. Only ask if something cannot be determined from the files.

### 1. Find the controller method
Read `app/Http/Controllers/{Resource}Controller.php` — each controller method maps to one Form Request class. The method signature tells you:
- The route parameters present (e.g. `Post $post`, `Comment $comment`) → used in `authorize()` via `$this->route('param')`
- Whether it's a collection or member endpoint → determines the policy action

### 2. Find or infer the Policy
Look in `app/Policies/{Resource}Policy.php` to confirm the available policy methods and their signatures. The policy method signature determines whether to pass a model class, a route model, or an array. If the policy does not exist, create one.

### 3. Find the model and migration
Read the model's `$fillable` and the migration columns to know what fields to validate in `rules()`. Cross-reference the migration for types, lengths, and nullable status.

### 4. One request class per controller method
Create one file per method. Name it after the action: `{Action}{Resource}Request.php` stored in `app/Http/Requests/{Resource}/`.

### 5. Only ask if truly blocked
If the controller, model, migration, and policy all don't exist yet, create them — otherwise infer from the route file and migration. Only ask if you truly need to verify or confirm something.

---

## Strict Rules

1. **Always extends `FormRequest`** — never the base `Request` class.
2. **Method order**: always `authorize()` → `rules()` → `messages()`.
3. **PHPDoc on every method** — `@return bool` for `authorize()`, `@return array` for `rules()` and `messages()`.
4. **`messages()` only when `rules()` is non-empty** — omit it entirely when `rules()` returns `[]`.
5. **No unused imports** — only import what is actually referenced in the file.
6. **Import model only when used as `ModelClass::class`** — if `authorize()` only uses `$this->route(...)`, do not import the model.
7. **All custom messages end with a period.**
8. **Update requests use `'sometimes'`** — never `'required'` on update; use `'sometimes'` for fields that are optional on update.

---

## Method Order and Structure

```php
class XxxRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool { ... }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array { ... }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages(): array { ... }  // only when rules() is non-empty
}
```

---

## `authorize()` Patterns

The policy action and argument shape depend on whether the resource is top-level or nested, and whether it's a collection or member endpoint.

### Top-level resource — collection endpoint
```php
// viewAny, create, deleteAny
return $this->user()->can('viewAny', Post::class);
return $this->user()->can('create', Post::class);
return $this->user()->can('deleteAny', Post::class);
```

### Top-level resource — member endpoint
```php
// view, update, delete, named actions
return $this->user()->can('view', $this->route('post'));
return $this->user()->can('update', $this->route('post'));
return $this->user()->can('delete', $this->route('post'));
```

### Nested resource — collection endpoint (parent in array)
```php
// viewAny, create — pass [ModelClass::class, $this->route('parent')]
return $this->user()->can('viewAny', [Comment::class, $this->route('post')]);
return $this->user()->can('create', [Comment::class, $this->route('post')]);
return $this->user()->can('deleteAny', Comment::class); // deleteAny never needs parent
```

### Nested resource — member endpoint (parent + model in array)
```php
// view, update, delete — pass [ModelClass::class, $this->route('parent'), $this->route('resource')]
return $this->user()->can('view', [Comment::class, $this->route('post'), $this->route('comment')]);
return $this->user()->can('update', [Comment::class, $this->route('post'), $this->route('comment')]);
return $this->user()->can('delete', [Comment::class, $this->route('post'), $this->route('comment')]);
```

### When to import the model class
| `authorize()` uses | Import model? |
|---|---|
| `Post::class` (class reference) | ✅ Yes |
| `$this->route('post')` (instance) | ❌ No |
| Array with `Comment::class` | ✅ Yes |

---

## `rules()` Patterns by Request Type

### Show / Delete single — no rules
```php
public function rules(): array
{
    return [];
}
```

### Delete bulk — ID array validation
```php
public function rules(): array
{
    return [
        'resource_ids'   => ['required', 'array', 'min:1'],
        'resource_ids.*' => ['uuid'],
    ];
}
```

### Create — required fields, nullable optionals
```php
public function rules(): array
{
    return [
        'title'       => ['required', 'string', 'max:255'],
        'body'        => ['nullable', 'string'],
        'status'      => ['required', Rule::in(StatusEnum::values())],
        'thumbnail'   => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:5120'],
    ];
}
```

### Update — `'sometimes'` replaces `'required'`
```php
public function rules(): array
{
    return [
        'title' => ['sometimes', 'string', 'max:255'],
        'body'  => ['nullable', 'string'],
    ];
}
```

### DB existence check (with scoping)
```php
'clone_from_id' => [
    'nullable',
    'uuid',
    Rule::exists('posts', 'id')->where('author_id', $this->route('author')->id),
],
```

### File upload
```php
'thumbnail' => ['required', 'file', 'image', 'max:2048'],
// or for specific mimes:
'thumbnail' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:5120'],
```

---

## `messages()` Patterns

Include one entry per `field.rule` combination. Always end with a period. Describe the field in plain English using the resource name.

### Standard field messages
```php
'title.required' => 'The post title is required.',
'title.string'   => 'The post title must be a string.',
'title.max'      => 'The post title must not exceed 255 characters.',
```

### Exact-length field (e.g. country code)
```php
'country.size' => 'The country code must be 2 characters.',
```

### Bulk IDs
```php
'resource_ids.required' => 'The resource IDs are required.',
'resource_ids.array'    => 'The resource IDs must be an array.',
'resource_ids.min'      => 'At least one resource ID is required.',
'resource_ids.*.uuid'   => 'Each resource ID must be a valid UUID.',
```

### File fields
```php
'thumbnail.required' => 'Please select an image file.',
'thumbnail.image'    => 'The file must be an image.',
'thumbnail.max'      => 'The image may not be larger than 2MB.',
```

---

## Import Order

```
1. Model classes (alphabetical)
2. Enum classes (alphabetical)
3. Service classes (alphabetical, only if called in rules())
4. Illuminate\Foundation\Http\FormRequest
5. Illuminate\Support\Arr         (only if used)
6. Illuminate\Validation\Rule     (only if used)
```

---

## Examples

Read the example files before writing any request — they are the authoritative reference for authorize() shapes, rules patterns, and messages style.

**Post requests** (`examples/Post/`):
- `CreatePostRequest.php` — required + nullable fields; file validation with mimes; full messages()
- `DeletePostRequest.php` — member delete; route model only in authorize(); no model import; empty rules()
- `DeletePostsRequest.php` — bulk delete; ModelClass::class in authorize(); array + uuid rules; bulk messages()
- `ShowPostRequest.php` — member view; route model only; empty rules(); no messages()
- `ShowPostsRequest.php` — collection viewAny; ModelClass::class in authorize(); empty rules()
- `UploadPostThumbnailRequest.php` — named action; update policy; file + image rules; file-specific messages()

**Comment requests** (`examples/Comment/`):
- `CreateCommentRequest.php` — nested resource create; array authorize(); Rule::exists with scope; Rule::in with service data
- `DeleteCommentRequest.php` — nested member delete; three-item array in authorize()
- `DeleteCommentsRequest.php` — bulk delete; deleteAny; standard bulk rules + messages
- `ShowCommentRequest.php` — nested member view; three-item array in authorize()
- `ShowCommentsRequest.php` — nested collection viewAny; two-item array in authorize()
- `UpdateCommentRequest.php` — nested member update; 'sometimes' rules; messages()

When in doubt about authorize() shape or rules structure — match the closest example exactly.

---

## Common Mistakes to Avoid

- ❌ Importing the model when `authorize()` only uses `$this->route(...)` — no import needed
- ❌ Using unused imports (`Arr`, `Rule`) when they are not referenced in the file
- ❌ Using `'required'` on update requests — always use `'sometimes'`
- ❌ Including `messages()` when `rules()` returns `[]` — omit it entirely
- ❌ Missing the period at the end of custom message strings
- ❌ Passing just `$this->route('resource')` for nested member endpoints — must be an array `[ModelClass::class, $this->route('parent'), $this->route('resource')]`
- ❌ Passing an array for top-level member endpoints — just pass `$this->route('resource')` directly
- ❌ Using `Rule::exists` or `Rule::in` without importing `Illuminate\Validation\Rule`
- ❌ Adding `messages()` returning `[]` — either populate it or omit it
