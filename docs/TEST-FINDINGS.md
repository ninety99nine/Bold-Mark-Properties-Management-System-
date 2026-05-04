# Test Findings — Bugs & Gaps Surfaced by the Test Suite

This document consolidates every issue discovered while writing the feature-test
suite (Auth, Estates, Units, Owners, Tenants, Organization, Users).

Each finding lists:
- **Where** — file paths and key lines
- **What** — the behaviour as it stands today
- **Why it matters** — user-facing or security impact
- **Fix sketch** — one-paragraph direction
- **Status** — `Open`, `Fixed in test branch`, or `Characterized only`

---

## 1. Security gaps (highest priority)

### 1.1 Cross-organization data access across every resource

**Where:** `EstatePolicy`, `UnitPolicy`, `TenantPolicy`, `OwnerPolicy`,
`UserPolicy`, plus `AppServiceProvider::registerRouteModelBindings()`
(`api/app/Providers/AppServiceProvider.php`).

**What:** All `view`/`update`/`delete` policy methods on these resources
either return `true` or only check a generic permission like
`estate.update`. None verify `$resource->organization_id === $user->organization_id`.
Route model binding (`Route::model('estate', Estate::class)` etc.) doesn't
organization-scope either, so guessing a UUID returns another
organization's record.

**Why it matters:** A logged-in admin can `GET`/`PUT`/`DELETE` records
belonging to other organizations by guessing IDs:

| Resource    | Cross-org `GET`                | Cross-org `PUT`           | Cross-org `DELETE`        |
|-------------|--------------------------------|---------------------------|---------------------------|
| Estate      | 200 (data leaked)              | 200 (writes succeed)      | 200 (record deleted)      |
| Unit        | 200 (with mismatched estate)   | 200                       | 200                       |
| Owner       | 200                            | 200                       | 200                       |
| Tenant      | 200                            | n/a (covered)             | n/a (covered)             |
| User        | 200                            | 200                       | (`delete` blocks self)    |

The bulk-delete services on Estate/Unit/Owner/Tenant **are** correctly
org-scoped — they `whereIn('id', $ids)->where('organization_id', $user->organization_id)`.
Single delete and update are not.

**Fix sketch:** Pick one:
1. **Org-scoped route bindings** — add `Route::bind('estate', fn ($v) =>
   Estate::where('id', $v)->where('organization_id', auth()->user()->organization_id)
   ->firstOrFail())` for each resource. This is the cleanest, app-wide fix.
2. **Policy-level checks** — extend each `view/update/delete` policy method
   to fail when `$resource->organization_id !== $user->organization_id`.

**Status:** `Open`. Tests in every resource suite include paired
`CHARACTERIZATION` (asserts current 200) and `SECURITY` (asserts the
correct 404, marked `->skip()`) blocks so the desired behaviour unlocks
automatically once the binding/policy is fixed.

---

### 1.2 Login has no rate-limiting / brute-force protection

**Where:** `api/app/Http/Controllers/Api/V1/Auth/AuthController::login`,
`api/routes/api.php`.

**What:** `POST /v1/auth/login` has no `throttle:` middleware and the
controller doesn't track failed attempts.

**Why it matters:** Unlimited credential-guessing per IP/email. Combined
with the user-enumeration issue below, an attacker can cheaply discover
real accounts and brute-force passwords.

**Fix sketch:** Add `throttle:5,1` (or a named limiter via
`RateLimiter::for('login', ...)`) to the unauthenticated auth route group
and surface the standard 429 response on lockout.

**Status:** `Open`.

---

### 1.3 User-enumeration on forgot-password

**Where:** `api/app/Http/Controllers/Api/V1/Auth/AuthController::forgotPassword`.

**What:** When the email doesn't exist, `Password::sendResetLink()` returns
`Password::INVALID_USER` and the controller returns `200` with the literal
translation *"We can't find a user with that email address."* — visibly
distinguishable from the success message.

**Why it matters:** Lets an attacker probe which emails belong to real
accounts before brute-forcing.

**Fix sketch:** Always respond with a constant generic 200 message
regardless of whether the user exists. Log the broker status server-side
if you need diagnostics.

**Status:** `Open`.

---

### 1.4 `/v1/branding` enables organization-existence fingerprinting

**Where:** `api/app/Http/Controllers/Api/V1/BrandingController::show`,
`api/routes/api.php`.

**What:** Public endpoint resolves the organization by host subdomain. Hitting
`acme.example.com` reveals whether `acme` is a real, active organization — the
response distinguishes resolved-org payload from the platform fallback.

**Why it matters:** Likely intentional for SaaS branding, but worth being
aware that the endpoint is an organization directory.

**Fix sketch:** If you want to hide existence, return the platform
fallback for unknown subdomains *with the same shape* as a resolved one
(no extra hints) — already mostly the case, but verify.

**Status:** `Open` — flagged for awareness; may be intentional.

---

## 2. Broken features (silent failures)

### 2.1 `User::estates()->sync(...)` crashed with NOT NULL on `user_estates.id`

**Where:** `api/app/Models/User::estates()`, `api/app/Models/Estate::assignedUsers()`,
migration `2026_04_06_000001_create_user_estates_table.php`.

**What:** The pivot table has `uuid('id')->primary()` with no default
generator, but the relationship used the default `BelongsToMany` Pivot
class which doesn't fill `id`. Any call to
`$user->estates()->sync($estateIds)` (the `PUT /v1/users/{user}/estates`
route) errored with `Integrity constraint violation: NOT NULL constraint
failed: user_estates.id`.

**Fix sketch:** Pivot model with `HasUuids`, wired via `->using(...)` on
both ends — same pattern used for `EstateChargeType`.

**Status:** `Fixed in test branch`. Created `api/app/Models/UserEstate.php`
and updated `User::estates()` + `Estate::assignedUsers()` to use it.

---

### 2.2 `TenantService::updateTenant()` silently dropped move-out fields

**Where:** `api/app/Services/TenantService::updateTenant` (after the
`UnitTenant` → `Tenant` rename, this is the unit-tenant service).

**What:** `UpdateTenantRequest` validates `move_out_date`,
`move_out_reason`, `move_out_notes`, and `UnitDetailPage.vue`'s
EditMoveOut modal `PUT`s exactly those fields — but the service's
`->only([...])` allow-list omitted them, so they round-tripped through
validation and were then discarded. The modal *appeared* to save and
showed no error.

**Fix sketch:** Add the three fields to the `->only([...])` allow-list.

**Status:** `Fixed in test branch`.

---

### 2.3 Sectional-title estate guard never fired

**Where:** `CreateUnitRequest::withValidator`, `UpdateUnitRequest::withValidator`.

**What:** The guard read `$estate->type === 'sectional_title'`, but
`Estate::$casts` makes `type` a `BackedEnum`. The strict comparison
`EnumInstance === 'string'` is always false, so sectional-title estates
silently accepted `tenant_occupied` units and `tenant.{...}` payloads
(the `tenant.*` keys here refer to the unit-occupant data block, not the
SaaS organization)
they were meant to reject.

**Fix sketch:** Extract the value before comparing
(`$estate->type instanceof BackedEnum ? $estate->type->value : $estate->type`).

**Status:** `Fixed in test branch`.

---

### 2.4 `?estate_id=…` filter on `/v1/owners` was dead

**Where:** `api/app/Http/Requests/Owner/ShowOwnersRequest::rules()`.

**What:** `OwnerService::showOwners` reads `$data['estate_id']` from
`$request->validated()`, but the request's `rules()` was empty — so
`validated()` stripped the param. The filter on the OwnerListPage was a
no-op.

**Fix sketch:** Whitelist `'estate_id' => ['nullable', 'uuid']` in rules.

**Status:** `Fixed in test branch`.

---

### 2.5 `?role=…` and `?status=…` filters on `/v1/users` were dead

**Where:** `api/app/Http/Requests/User/ShowUsersRequest::rules()`.

**What:** Same root cause as 2.4 — empty `rules()` stripped both query
params before they reached the service.

**Fix sketch:** Whitelist `role` (with `exists:roles,name`) and `status`
(with `Rule::in(UserStatus::values())`) in rules.

**Status:** `Fixed in test branch`.

---

### 2.6 `?is_active=1` filter on `/v1/.../tenants` was dead

**Where:** `api/app/Http/Requests/Tenant/ShowTenantsRequest::rules()`
(the unit-tenants index request),
plus the strict `=== 'true' || === true || === 1` check in
`TenantService::showTenants`.

**What:** Same dead-rules issue as 2.4/2.5, *plus* the service's
truthy-check uses strict equality. URL params arrive as strings, so
`is_active=1` (string `'1'`) failed all three branches and the filter
fell through to false (returning the inactive set instead of the active
set).

**Fix sketch:** Add `prepareForValidation` mirroring `ShowEstatesRequest`
that coerces via `filter_var(..., FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`,
then add `'is_active' => ['nullable', 'boolean']` in rules.

**Status:** `Fixed in test branch`.

---

### 2.7 `EstateService::showTenantAnalytics` doesn't exist

**Where:** `api/routes/api/estates.php` registers
`GET /estates/{estate}/tenant-analytics` → `EstateController::tenantAnalytics`
→ `$this->service->showTenantAnalytics($estate)`. The method is not
defined on `EstateService`.

**What:** Calling the route returns 500 (`BadMethodCallException` /
undefined method).

**Fix sketch:** Implement the method or remove the route until it's ready.

**Status:** `Open`. Test for the endpoint is `->skip()`-ed with a
referencing reason.

---

### 2.8 `BrandingController` references a non-existent `accent_color` column

**Where:** `api/app/Http/Controllers/Api/V1/BrandingController::show`,
migration `2026_03_30_200000_create_organizations_table.php`.

**What:** The fallback (no-match) branch hard-codes
`'accent_color' => '#D89B4B'`, but the resolved-org branch reads
`$org->accent_color` — and the `organizations` table has no such column.
Resolved organizations always return `accent_color: null` while the
fallback returns a real colour. Frontend gets inconsistent shape.

**Fix sketch:** Either add an `accent_color` column to the
organizations migration *and* the `Organization` fillable /
`OrganizationResource`, or change the controller to read
`secondary_color` (which exists).

**Status:** `Open`. Characterization test asserts the current `null`.

---

### 2.9 Invitations password broker has `expire => 0` (always expired)

**Where:** `api/config/auth.php`, the `passwords.invitations` broker.

**What:** Comment says "0 = never expires"; Laravel's
`PasswordBroker::tokenExpired()` does `Carbon::parse($createdAt)->addMinutes(0)
->isPast()` — which is `true` immediately. Every token issued by
`Password::broker('invitations')` is dead on arrival.

**Why it matters:** New invited users (`UserService::inviteUser` issues
the token via this broker) cannot complete password setup through the
"invitations" broker path. The reset attempt fails with "expired token".

**Fix sketch:** Set `expire` to a large positive integer (e.g.
`60 * 24 * 30` for 30 days) instead of `0`.

**Status:** `Open`. Auth tests skip the invitations-broker round-trip
case for this reason.

---

### 2.10 `copyright_name` cannot be updated via `PUT /v1/tenant`

**Where:** `api/app/Services/OrganizationService::updateOrganization`
allow-list,
`api/app/Http/Requests/Organization/UpdateOrganizationRequest::rules()`.

**What:** The service's `->only([...])` *includes* `copyright_name`, but
the request's `rules()` does not validate it. `validated()` strips it
before reaching the service, so the field can never be updated through
the endpoint.

**Fix sketch:** Add
`'copyright_name' => ['nullable', 'string', 'max:255']` to the rules — or
remove it from the service if it shouldn't be editable.

**Status:** `Open`. Characterization test asserts the current "drop"
behaviour.

---

## 3. Schema vs. validation mismatches

### 3.1 `organizations.contact_phone` column 20 chars, validator allows 30

**Where:** Migration `2026_03_30_200000_create_organizations_table.php`
(`string('contact_phone', 20)`) vs `UpdateOrganizationRequest`
(`'contact_phone' => ['nullable', 'string', 'max:30']`).

**Why it matters:** A 21–30 char phone passes validation but errors at
the DB layer with a 500.

**Fix sketch:** Tighten the rule to `max:20` or widen the column to 30.

**Status:** `Open`.

---

### 3.2 `organizations.country` column 3 chars, validator requires exactly 2

**Where:** Migration (`string('country', 3)`, default `'ZA'`) vs
`UpdateOrganizationRequest` (`'country' => ['sometimes', 'string', 'size:2']`).

**Why it matters:** Validator is stricter than the schema (good), but
the column width is now misleading and reads like ISO 3166 alpha-3 was
ever supported.

**Fix sketch:** Shrink the column to `string('country', 2)` to make the
schema match the validator's intent.

**Status:** `Open`.

---

## 4. Broken model factories

### 4.1 Enum-case mismatches in `Unit` and `Estate` factories

**Where:** `api/database/factories/UnitFactory.php`,
`api/database/factories/EstateFactory.php`.

**What:** The factories' state methods reference enum cases in
PascalCase — `UnitStatus::Active`, `UnitStatus::Suspended`,
`OccupancyType::OwnerOccupied`, `OccupancyType::TenantOccupied`,
`OccupancyType::Vacant`, `EstateType::SectionalTitle`, etc. — but the
enums use `UPPER_SNAKE_CASE` (`UnitStatus::ACTIVE`, etc.). PHP enum case
lookup is case-sensitive, so any call to `Unit::factory()->vacant()` or
`Estate::factory()->sectionalTitle()` throws `Error: Undefined constant`.

**Why it matters:** The default factory `definition()` for `Unit` was
also broken because it referenced `UnitStatus::Active->value` — every
`Unit::factory()->create(...)` call crashed. (This was the root cause of
~half of the pre-existing test failures we inherited.)

**Fix sketch:** Rename every reference to the actual case constant
(e.g. `UnitStatus::ACTIVE`).

**Status:** `Fixed in test branch` — only the default `definition()`
reference was patched. The state methods (`->vacant()`, `->ownerOccupied()`,
`->tenantOccupied()`, `->suspended()`, plus the `EstateFactory` ones)
are still broken; new tests bypass them by passing `occupancy_type`/`status`
directly.

---

## 5. Test infrastructure gaps

### 5.1 SQLite test DB lacks `GREATEST` / `LEAST`

**Where:** Test environment (`phpunit.xml` uses `DB_CONNECTION=sqlite`)
vs. production (Postgres). `UnitService::showUnits` uses
`COALESCE(SUM(GREATEST(0, ...)))` for outstanding-amount subqueries.

**Why it matters:** Every Units index/show test was 500-ing with
`no such function: GREATEST`.

**Fix sketch:** Register them as SQLite UDFs in `tests/Pest.php`'s
global `beforeEach`.

**Status:** `Fixed in test branch`. UDFs registered via
`$pdo->sqliteCreateFunction(...)` keyed by `spl_object_id($pdo)` so they
only register once per connection.

---

### 5.2 Postgres-only `ilike` blocks SQLite search tests

**Where:** Search scopes on `Estate`, `Unit`, `Owner`, `Tenant`,
`User`, `Organization` (all use `where(..., 'ilike', '%term%')`).

**Why it matters:** SQLite errors out on `ilike`; every `_search` test
is `->skip()`-ed.

**Fix sketch:** Make search scopes DB-agnostic — e.g.
`whereRaw('LOWER(<col>) LIKE ?', ['%' . strtolower($term) . '%'])` or
branch on `$query->getConnection()->getDriverName()`.

**Status:** `Open`. All search tests are skipped with a referencing
reason so they unlock once this is portable.

---

### 5.3 Spatie role guard mismatch in `Pest.php`

**Where:** `tests/Pest.php`'s `createUser()` helper creates Spatie roles
with `'guard_name' => 'web'`, but the API runs through `auth:api`.

**Why it matters:** Calls like `$user->assignRole('portfolio-manager')`
inside the API request context throw
`There is no role named X for guard api`.

**Fix sketch:** Create roles with `'guard_name' => 'api'` in the helper,
*or* duplicate the role for both guards if you need parity.

**Status:** Worked around in `UsersTest` by using a local `ensureRole`
helper that creates with `'guard_name' => 'api'`. The global helper in
`Pest.php` should be aligned for consistency.

---

### 5.4 Passport `TokenGuard` caches the resolved user across requests in tests

**Where:** `vendor/laravel/passport/src/Guards/TokenGuard.php` (the
`if (! is_null($this->user)) return $this->user;` early-return).

**Why it matters:** In production each HTTP request is a fresh PHP-FPM
process, so the cache is fine. In tests, sequential `->getJson()` calls
in the same `it()` block reuse the same kernel — the guard's cached
`$this->user` persists, so revoking a token (logout, password reset)
appears to do nothing on the *next* request in the same test.

**Fix sketch:** Helper that calls `Auth::forgetGuards()` between
sequential token-changing requests in tests.

**Status:** `Fixed in test branch` via the `forgetAuth()` helper in
`AuthTest.php`.

---

## 6. Design quirks worth a second look

### 6.1 Bulk-import for units has overlapping validation layers

**Where:** `ImportUnitsRequest` rules vs in-service per-row validation
in `UnitService::bulkImportUnits`.

**What:** Both layers validate `unit_number`/`occupancy_type`/
`owner_full_name`/`owner_email`. A row missing any of these is rejected
by the request layer with 422 — so the service's per-row `errors[]`
array effectively only ever surfaces (a) duplicates and (b) invalid
`tenant_email` (the only field validated only at the service layer).

**Why it matters:** If the frontend uploads a 500-row CSV with one bad
row, the entire batch fails with 422 instead of importing the 499 good
rows and reporting the one error. May or may not be the intended UX.

**Fix sketch:** Decide where validation lives. If the per-row error UX
(used by `BulkImportUnitsModal.vue`) is the intended pattern, drop the
overlapping rules from `ImportUnitsRequest` so the request only enforces
`rows: required|array|min:1` and the service does the per-row checks.

**Status:** `Open`.

---

### 6.2 `_relationships` and `_countable_relationships` query params are unimplemented

**Where:** `BaseService` has no handler for these. Resources
(`EstateResource`, `UnitResource`, `OwnerResource`, `UserResource`,
etc.) use `whenLoaded(...)` / `whenCounted(...)`, but no service-side
code reads the query string to call `with(...)` / `withCount(...)`.

**Why it matters:** The frontend appears to send these params (the old
test suite tested them), but they're silently ignored — nothing is
eager-loaded.

**Fix sketch:** Add a `applyEagerLoadFromRequest()` to `BaseService` that
reads `_relationships` / `_countable_relationships`, validates against a
per-service whitelist of allowed relations, and calls `with()` /
`withCount()` accordingly.

**Status:** `Open`. Old tests for these were removed; nothing actively
asserts they work.

---

### 6.3 Bulk-delete policies deny *before* validation

**Where:** `EstatePolicy::deleteAny`, `UnitPolicy::deleteAny`,
`OwnerPolicy::deleteAny`, `TenantPolicy::deleteAny`,
`UserPolicy::deleteAny` — all return `false` when
`request()->input('*_ids')` is empty.

**What:** Sending `DELETE /…` with no body or `*_ids: []` returns 403
instead of 422. By design — the policy guard prevents an empty payload
from masquerading as "delete everything I'm allowed to". But the UX is
arguably worse than a clear 422 with a validation error message.

**Fix sketch:** Either keep as-is (status quo) and document, or move the
"non-empty" check into the request's `rules()` (`required|array|min:1`)
and let the policy purely answer the permission question.

**Status:** `Open`. Tests assert the current 403 behaviour.

---

### 6.4 `Reinstate` throws raw `Exception` (becomes 500)

**Where:** `TenantService::reinstateTenant` throws
`new Exception('This unit already has an active tenant. Move them out
before reinstating another.')` when the unit already has an active
tenant.

**Why it matters:** Surfaces as a generic 500 to the frontend. The
modal in `UnitDetailPage.vue` shows a network failure rather than the
inline message.

**Fix sketch:** Throw a `\Illuminate\Validation\ValidationException` (or
a custom 409 exception) so the frontend gets a structured payload it can
render.

**Status:** `Open`.

---

### 6.5 Lease document mime list doesn't include `.docx`

**Where:** `UploadLeaseDocumentRequest`:
`'lease_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png',
'max:5120']`.

**Why it matters:** Property managers often hold leases as Word docs;
they'd have to convert to PDF before uploading.

**Fix sketch:** Add `docx,doc` to the `mimes:` list if Word is
intentionally supported — confirm with stakeholders.

**Status:** `Open` — flagged for product decision.

---

### 6.6 Bulk-import-parse rejects partial multipart with redirect-back

**Where:** `BulkImportUnitsRequest`, file upload tests.

**What:** When validation fails on a multipart file upload, Laravel
defaults to a 302 redirect-back unless the request has
`Accept: application/json`. The frontend `axios` setup does send
`Accept: application/json`, so production is fine — but tests using
`$this->post(...)` instead of `$this->postJson(...)` won't work without
adding the header.

**Fix sketch:** Tests use `withHeaders(['Accept' => 'application/json'])`
before `->post(...)` for file uploads. Documented in the affected tests.

**Status:** `Documented` — not a real bug, just a test-author trap.

---

### 6.7 Email uniqueness on users is global (not per-tenant)

**Where:** `InviteUserRequest`: `'email' => [..., 'unique:users,email']`.

**What:** Inviting a user with an email that exists on a foreign tenant
fails with `email` validation error. The constraint is global.

**Why it matters:** Likely intentional for SSO/login simplicity (every
user logs in with email, so emails must be unique platform-wide). But
worth being explicit — the `users.email` column has a `unique()` index
in the migration, so this is enforced at the DB level too.

**Fix sketch:** If multi-tenancy ever wants the same email across
organizations, swap to `Rule::unique('users', 'email')->where('organization_id', $orgId)`
*and* drop the unique index from the migration.

**Status:** `Open` — flagged for product decision. Test asserts the
current global-uniqueness behaviour.

---

### 6.8 Frontend uses `per_page` (not `_per_page`) in one place

**Where:** `web/src/components/common/AllocationModal.vue`:
`api.get('/estates/${estate_id}/units', { params: { per_page: 200 } })`.

**What:** Every other frontend caller uses `_per_page`. The `_per_page`
prefix is what `BaseService` reads. So this call silently falls back to
the default 15.

**Fix sketch:** One-line change in `AllocationModal.vue` —
`per_page` → `_per_page`.

**Status:** `Open`.

---

## Reference: where each finding came from

| Finding   | Surfaced in suite     |
|-----------|------------------------|
| 1.1       | Estates, Units, Owners, Tenants, Users |
| 1.2, 1.3  | Auth                   |
| 1.4       | Organization          |
| 2.1       | Users                  |
| 2.2       | Tenants            |
| 2.3       | Units                  |
| 2.4       | Owners                 |
| 2.5       | Users                  |
| 2.6       | Tenants            |
| 2.7       | Estates                |
| 2.8, 2.10 | Organization          |
| 2.9       | Auth, Users            |
| 3.1, 3.2  | Organization          |
| 4.1       | Estates, Units         |
| 5.1, 5.4  | Auth, Units            |
| 5.2       | Every suite (search)   |
| 5.3       | Users                  |
| 6.1       | Units                  |
| 6.2       | Estates, Owners, Units |
| 6.3       | Estates, Units, Owners, Tenants, Users |
| 6.4, 6.5, 6.6 | Tenants        |
| 6.7       | Users                  |
| 6.8       | Units                  |

## Reference: what's already fixed in this branch

The following changes shipped alongside the test work:

- `api/app/Models/UserEstate.php` (new) — pivot model with `HasUuids`.
- `api/app/Models/User.php`, `Estate.php` — `->using(UserEstate::class)`.
- `api/app/Http/Requests/Owner/ShowOwnersRequest.php` — added `estate_id`.
- `api/app/Http/Requests/User/ShowUsersRequest.php` — added `role` + `status`.
- `api/app/Http/Requests/Tenant/ShowTenantsRequest.php` — added
  `is_active` + boolean coercion via `prepareForValidation`.
- `api/app/Http/Requests/Unit/CreateUnitRequest.php`,
  `UpdateUnitRequest.php` — sectional-title comparison now extracts the
  enum value.
- `api/app/Services/TenantService.php` — `updateTenant` now
  persists `move_out_*` fields.
- `api/database/factories/UnitFactory.php` — `UnitStatus::Active` →
  `UnitStatus::ACTIVE` in the default `definition()`.
- `api/tests/Pest.php` — global `beforeEach` registers SQLite
  `GREATEST` / `LEAST` UDFs.
- **Resource rename**: the SaaS-org concept (previously `Tenant`) →
  `Organization`; the unit-occupant concept (previously `UnitTenant`) →
  `Tenant`. This affects models, migrations (`tenants` → `organizations`,
  `unit_tenants` → `tenants`, every `tenant_id` FK → `organization_id`),
  controllers, services, resources, requests, policies, factories,
  routes, and the frontend `useOrganizationStore`. The cross-organization
  policy gap from §1.1 still applies — naming is clearer now but the
  authorization checks themselves haven't been added yet.
