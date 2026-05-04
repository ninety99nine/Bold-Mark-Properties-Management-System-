# Development Log — Bold Mark Properties Management System

> This file tracks all development changes made to the system, organised by date.
> It is maintained for client communication — Justin and the Bold Mark team can review
> this log to understand exactly what was built, fixed, or improved on each working day.

---

## 23 April 2026

### Summary

Phase 2 build — added the Arrears dashboard, Vacancies dashboard, Risk Rules engine, notification system, global search, and multi-country support. The app now provides actionable debt recovery views, vacancy tracking with revenue-loss estimates, and configurable risk flags. All pages are country-scoped for multi-region tenants.

---

### Frontend — New Pages & Features

**Arrears Page (new: `ArrearsPage.vue`)**
- Full arrears dashboard at `/arrears` showing units with overdue invoices
- Summary cards: units in arrears, arrears rate, total overdue amount, estates affected
- Paginated, sortable, searchable table of overdue units with color-coded overdue-day badges
- Estate breakdown cards showing arrears per estate (up to 4 visible, modal for more)
- Duration distribution bar chart (0–30, 30–60, 60–90, 90+ days)
- Estate type breakdown doughnut chart
- Charge type breakdown doughnut chart (top 8)
- Top 10 debtors section ranked by overdue amount
- Filters: search, date range, estate, estate type, charge type, per page

**High Risk Section (new: `HighRiskSection.vue`)**
- Sub-component embedded in the Arrears page
- Displays units flagged by risk rule evaluation with severity indicators (Critical / Warning)
- Shows matched rules and key metrics (overdue amount, days overdue, invoice count)

**Vacancies Page (new: `VacanciesPage.vue`)**
- Full vacancies dashboard at `/vacancies` replacing the placeholder from April 22
- Summary cards: total vacant, vacancy rate, total units, estates affected
- Estate breakdown cards with vacancy counts per estate
- Paginated, sortable, searchable table of vacant units
- Estate type breakdown chart
- Occupancy per estate grouped bar chart (vacant vs occupied)
- Estimated lost monthly revenue per estate (top 10, bar chart)
- Vacancy duration distribution (0–30, 30–90, 90–180, 180+ days)
- Filters: search, date range, estate, estate type, per page

**Sidebar Navigation (`AppSidebar.vue`)**
- Added "Collections" as a new collapsible parent menu containing "Arrears" and "Age Analysis"
- Age Analysis moved from standalone item into the Collections group
- Sidebar now has two collapsible groups: Portfolio (Estates, Vacancies) and Collections (Arrears, Age Analysis)

**Topbar (`AppTopbar.vue`)**
- Added country switcher dropdown — visible only for multi-country tenants
- Shows country flag + name; persists selection to localStorage
- Added notification bell icon with unread count badge
- Notification dropdown shows the latest 30 notifications with type, message, timestamp, and read status
- Mark individual or all notifications as read
- Click a notification to navigate to the relevant resource
- Detail-route awareness: switching country from a detail page redirects to the parent list page

**App Layout (`AppLayout.vue`)**
- Integrated country store initialisation on mount
- Country store is fetched on app load and watched for changes across all pages

**Dashboard Page (`DashboardPage.vue`)**
- Country-scoped: watches country selection and refetches all data
- Currency formatting now uses `countryStore.formatCurrency()` instead of hard-coded "R" prefix
- Estates display capped at 4 items (down from 5)

**Billing Page (`BillingPage.vue`)**
- Country-scoped: all API calls pass the selected country parameter
- Currency formatting uses country store
- Supports `?action=run-billing` query parameter for direct navigation to billing run

**Cashbook Page & Entry Detail (`CashbookPage.vue`, `CashbookEntryDetailPage.vue`)**
- Country parameter added to all API calls for country-scoped data

**Estates Page & Estate Detail (`EstatesPage.vue`, `EstateDetailPage.vue`)**
- Country parameter added to all API calls
- Currency formatting uses country store

**Other Updated Pages**
- `InvoiceDetailPage.vue`, `TenantDetailPage.vue`, `UnitDetailPage.vue`, `OwnerDetailPage.vue`, `SettingsPage.vue` — updated currency formatting and country-scoped API calls where applicable
- `AgeAnalysisPage.vue` — country-scoped API calls, currency formatting via country store

**Router (`router/index.js`)**
- Added route `/arrears` → `ArrearsPage.vue`
- Updated route `/vacancies` to point to the full `VacanciesPage.vue` (was placeholder)

---

### Frontend — New Stores

**Country Store (new: `country.js`)**
- Pinia store for multi-country support
- Fetches available countries from `/dashboard/countries` API
- Tracks selected country with localStorage persistence
- Exposes: `activeCountry`, `currencySymbol`, `currencyCode`, `isMultiCountry`
- `formatCurrency(amount)` — full format with country-specific symbol (e.g. "R 1,234.56", "P 500.00")
- `formatCurrencyCompact(amount)` — compact format for charts (e.g. "R 50k", "P 2.5M")
- Supported countries: ZA (ZAR, R), BW (BWP, P), NA (NAD, N$), US (USD, $), GB (GBP, £)

**Notifications Store (new: `notifications.js`)**
- Pinia store for in-app notifications
- Fetches latest 30 notifications with unread count
- Methods: `fetch()`, `markAsRead(id)`, `markAllAsRead()`

---

### Backend — New Features

**Arrears System**

*ArrearsController (new)*
- `GET /arrears` — returns paginated units with overdue invoices plus comprehensive analytics

*ArrearsService (new)*
- Paginated list of units with overdue invoices (joins estates, owners)
- Summary stats: units_in_arrears, total_units, total_overdue_amount, total_overdue_invoices, arrears_rate, estates_affected
- Breakdown by estate (top 10), by duration buckets (0–30, 30–60, 60–90, 90+ days)
- Breakdown by estate type and charge type
- Top 10 debtors by overdue amount
- Supports filtering by country, estate, estate_type, charge_type
- Supports search on unit_number, owner name, estate name
- Custom sort handling for arrears-specific fields

**Vacancy System**

*VacancyController (new)*
- `GET /vacancies` — returns paginated vacant units plus vacancy analytics

*VacancyService (new)*
- Paginated list of vacant units (joins estates, owners)
- Summary stats: total_vacant, total_units, vacancy_rate, estates_affected
- Breakdown by estate type and by estate
- Occupancy per estate (vacant vs occupied counts)
- Estimated lost monthly revenue per estate (top 10)
- Vacancy duration buckets (0–30, 30–90, 90–180, 180+ days)
- Supports filtering by country, estate, estate_type
- Custom sort handling for unit-related fields

**Risk Rules Engine**

*RiskRule Model (new)*
- Configurable risk assessment rules with JSON conditions
- Fields: name, description, severity (Warning/Critical), is_active, sort_order, conditions
- Scopes: `search()`, `active()`
- Scoped to tenant

*RiskSeverity Enum (new)*
- Values: `WARNING`, `CRITICAL`

*RiskRuleController (new)*
- `GET /risk-rules` — paginated list with optional `is_active` filter
- `POST /risk-rules` — create new rule
- `PUT /risk-rules/{riskRule}` — update rule
- `DELETE /risk-rules/{riskRule}` — delete rule
- `GET /risk-rules/evaluate` — evaluate all active rules against units in arrears
- `PUT /risk-rules/reorder` — reorder rules by sort_order

*RiskRuleService (new)*
- CRUD operations with auto-incrementing sort_order
- Rule evaluation engine: computes 4 metrics per unit (overdue_amount, overdue_invoice_count, days_overdue, arrears_rate)
- Conditions checked with AND logic within a rule, OR logic across rules
- Returns flagged units sorted by severity (critical first), then by overdue amount
- Tracks `flagged_count` per rule for analytics

*RiskRulePolicy (new)*
- Standard policy with super-admin bypass
- All actions permitted for authorised users

*Request classes (new)*
- `ShowRiskRulesRequest` — validates `is_active` boolean filter
- `CreateRiskRuleRequest` — validates name, description, severity, conditions array
- `UpdateRiskRuleRequest` — same rules with `sometimes` modifier
- `DeleteRiskRuleRequest` — authorisation check

*Resources (new)*
- `RiskRuleResource` / `RiskRuleResources` — JSON transformation for risk rules

**Notification System**

*NotificationController (new)*
- `GET /notifications` — returns latest 30 notifications with unread count
- `POST /notifications/{id}/mark-read` — mark individual notification as read
- `POST /notifications/mark-all-read` — mark all as read

*BillingRunCompleted Notification (new)*
- Database notification sent after billing runs
- Includes estate_id, estate_name, invoice_count, billing_period

**Global Search**

*SearchController (new)*
- `GET /search?q=...` — cross-model search endpoint
- Searches estates, units, people (owners/tenants/users), and invoices
- Returns up to 5 results per category
- Query parameter validated: 1–100 characters

**Multi-Country Support**

*CountryHelper (new)*
- Static helper for country/currency mappings
- `all()`, `get(code)`, `currencyCode(code)`, `currencySymbol(code)`, `options()`
- Supports: ZA (ZAR, R), BW (BWP, P), NA (NAD, N$), US (USD, $), GB (GBP, £)

*DashboardController*
- New `getCountries()` endpoint returning countries with estates, default country, and full country list
- `getDashboardSummary()` now accepts optional `country` parameter

*DashboardService*
- Country-scoped dashboard queries: filters estate-related stats by selected country
- Country JOIN applied to total_estates, total_outstanding, occupancy calculations

---

### Backend — Enhancements to Existing Code

**Search Improvements (multiple models)**
- `Estate`, `Unit`, `User`, `Owner`, `Tenant`, `UnitTenant` — changed `like` to `ilike` for case-insensitive search across all models
- `Invoice` — enhanced search with fuzzy matching: searches invoice_number with `ilike` and also matches when users omit dashes/spaces (normalised comparison)

**Estate Service (`EstateService.php`)**
- `showEstates()` and `showEstatesSummary()` now accept country filter
- Create and update operations handle `country` and `currency` fields

**Estate Requests**
- `CreateEstateRequest` — added `country` (nullable, max 3) and `currency` (nullable, max 3) validation
- `UpdateEstateRequest` — added `country` and `currency` with `sometimes` modifier

**Estate Resource (`EstateResource.php`)**
- Now includes `country` and `currency` fields in API response

**AppServiceProvider**
- Registered `RiskRulePolicy` for `RiskRule` model
- Added `RiskRule` route model binding

**DatabaseSeeder**
- Updated Passport client creation: `createPersonalAccessClient()` → `createPersonalAccessGrantClient()` (deprecated method fix)

---

### Database

**New Migration: `2026_04_23_000001_create_notifications_table.php`**
- Creates `notifications` table: id (uuid), type, notifiable_type, notifiable_id, data (text), read_at (nullable timestamp), timestamps
- Index on notifiable_type + notifiable_id

**New Migration: `2026_04_23_000002_create_risk_rules_table.php`**
- Creates `risk_rules` table: id (uuid), name (100), description (text, nullable), severity (20), is_active (boolean, default true), conditions (json), tenant_id (uuid, foreign key with cascade delete)
- Index on tenant_id + is_active

**New Migration: `2026_04_23_000003_add_sort_order_to_risk_rules_table.php`**
- Adds `sort_order` (unsigned integer, default 0) to `risk_rules` table

---

### API Routes (all new)

| File | Routes |
|---|---|
| `arrears.php` | `GET /arrears` |
| `vacancies.php` | `GET /vacancies` |
| `risk-rules.php` | `GET /risk-rules`, `POST /risk-rules`, `GET /risk-rules/evaluate`, `PUT /risk-rules/reorder`, `PUT /risk-rules/{riskRule}`, `DELETE /risk-rules/{riskRule}` |
| `notifications.php` | `GET /notifications`, `POST /notifications/mark-all-read`, `POST /notifications/{id}/mark-read` |
| `search.php` | `GET /search` |
| `dashboard.php` | `GET /dashboard/countries` (new endpoint added to existing file) |

---

## 22 April 2026

### Summary

Major session covering UI/UX improvements, backend data accuracy fixes, deployment infrastructure setup, sidebar navigation restructuring, and demo data updates to reflect real Botswana context.

---

### Frontend — UI/UX Improvements

**Dashboard Page (`DashboardPage.vue`)**
- Added pagination to the Recent Invoices panel (5 per page with Previous/Next controls) — previously showed all invoices in an unbounded list
- Limited Estates Overview panel to show a maximum of 5 estates
- Stat cards are now clickable — "Total Estates" links to `/estates`, "Total Outstanding" links to `/billing?status=unpaid`, "Collected This Month" links to `/cashbook`, "Occupancy Rate" links to `/estates`
- Stat card subtitles now show dynamic counts: e.g. "3 unpaid invoices" instead of generic "Unpaid invoices", "7 payments this month" instead of "Credits this month"
- Fixed the "Upload Cashbook" getting-started step: now correctly checks `total_cashbook_entries > 0` instead of relying on `collected_this_month` amount (which could be zero even with debit entries recorded)

**Sidebar Navigation (`AppSidebar.vue`)**
- Restructured navigation: "Estates" is now nested under a new collapsible "Portfolio" parent menu
- Added "Vacancies" as a new menu item under Portfolio (route: `/vacancies`)
- Portfolio section is expanded by default; clicking the parent toggles collapse/expand
- Active state detection works for both parent and child items

**Stat Card Component (`AppStatCard.vue`)**
- Added optional `to` prop — when provided, the card becomes clickable with hover effects (accent border, shadow lift, press scale)
- Maintains backward compatibility — cards without `to` behave as before

**Estates Page (`EstatesPage.vue`)**
- Added a "Vacant" summary card (5th card) showing total vacant units across all estates
- Summary cards grid updated from 4 columns to 5 columns

**Estate Detail Page (`EstateDetailPage.vue`)**
- Top Arrears chart bars are now clickable — clicking a bar navigates to that unit's detail page
- Improved chart axis formatting: amounts below R 1,000 show as "R 500" instead of "R 0.5k"
- Chart cursor changes to pointer on hover for better affordance

**Billing Page (`BillingPage.vue`)**
- Restyled the Invoices/Trash tab toggle: active tab now uses navy fill (`#1F3A5C`) with white text instead of plain white with border
- Added a count badge on the Trash tab showing total deleted invoices (red tint when inactive, white tint when active)

**Cashbook Entry Detail Page (`CashbookEntryDetailPage.vue`)**
- Rearranged the detail card layout: moved "Type" (Credit/Debit badge) from the left column to the right column, next to the amount
- Removed redundant "Allocation Status" display from the right column (this info is already shown elsewhere on the page)

**Age Analysis Page (`AgeAnalysisPage.vue`)**
- Added "Group by Person" toggle — when enabled, rows for the same person+unit are consolidated into a single row with summed bucket amounts (e.g. if someone owes Levy + Parking, they appear as one row with combined totals)
- Grouped view shows comma-separated charge types (e.g. "Levy, Parking Rental") instead of a single type
- Sorting is now applied after grouping, so grouped rows sort correctly by total, name, or unit

**Vacancies Page (new)**
- Added route `/vacancies` with placeholder page component
- Linked from the new Portfolio > Vacancies sidebar item

---

### Backend — Data & Logic Fixes

**Dashboard Service (`DashboardService.php`)**
- Added `unpaid_invoices_count` to the dashboard summary query — returns the count of invoices with status unpaid/overdue/partially_paid
- Added `payments_this_month_count` — returns the count of credit cashbook entries in the current month
- Added `total_cashbook_entries` — returns the total count of all cashbook entries (used to determine if the cashbook has been used at all)

**Estate Service (`EstateService.php`)**
- Added `vacant` count to the estates summary endpoint — counts all units with `occupancy_type = vacant`

**Unit Service (`UnitService.php`)**
- Fixed Top Arrears calculation on the estate detail page: now uses the stored `balance` column on units (maintained by `UnitBalanceService`) instead of summing raw invoice amounts
- This correctly accounts for partial payments, split payments, and unallocated credits — previously the arrears chart could overstate amounts for units with partial payments

**Age Analysis Service (`AgeAnalysisService.php`)**
- Major refactor: the age analysis now accounts for unallocated credit cashbook entries when computing arrears
- Credits are applied oldest-first (120+ days first, then 90, 60, 30, current) to reduce the most overdue buckets first
- Grouped entries by `unit_id` for credit offset calculations, ensuring credits for a specific unit reduce that unit's arrears correctly
- Added import for `CashbookEntry` model and `CashbookEntryType` enum

**Estate Model (`Estate.php`)**
- Added `country` and `currency` to the `$fillable` array to support per-estate country/currency settings

---

### Database & Seeding

**New Migration: `2026_04_21_000001_add_country_currency_to_estates_table.php`**
- Adds `country` (string, nullable) and `currency` (string, nullable) columns to the `estates` table

**Demo Users Seeder (`DemoUsersSeeder.php`)**
- Updated admin user: "Justin Sobhee" → "Justin Justin" with email `justin@boldmarkprop.co.za` (matches the real client contact)
- Added Julian Tabona as a second company-admin user (`brandontabona@gmail.com`)
- Updated all demo phone numbers from +27 (South Africa) to +267 (Botswana) prefix to reflect the Botswana-first deployment

**Demo Estates Seeder (new: `DemoEstatesSeeder.php`)**
- New seeder file for demo estate data (ready but not yet wired into the main seed chain)

**Demo External Users Seeder (new: `DemoExternalUsersSeeder.php`)**
- New seeder for external user roles (trustees, owners, tenants, contractors) with Botswana-appropriate names and data

**Roles & Permissions Seeder (`RolesAndPermissionsSeeder.php`)**
- Added `tenant` role (unit occupant, external)
- Added `contractor` role (maintenance provider, external)
- Both created with `guard_name: api`

**Database Seeder (`DatabaseSeeder.php`)**
- Updated to include the new seeders in the demo seed chain

---

### Infrastructure & Deployment

**Docker Setup (all new files)**
- `Dockerfile` — PHP 8.3 FPM container with all required extensions, Composer dependencies, Laravel optimisation
- `Dockerfile.nginx` — Nginx reverse proxy container
- `docker-compose.yml` — Full stack: PHP-FPM, Nginx, MySQL 8.0, Redis, with health checks and persistent volumes
- `docker/php/php.ini` — PHP configuration (memory limits, upload sizes, OPcache)
- `docker/php/www.conf` — PHP-FPM pool configuration
- `docker/mysql/my.cnf` — MySQL configuration
- `docker/scripts/entrypoint.sh` — Container entrypoint (runs migrations, caches config)
- `docker/scripts/healthcheck.sh` — Container health check script
- `docker/scripts/deploy.sh` — Deployment automation script
- `docker/scripts/first-deploy.sh` — First-time server setup script
- `docker/scripts/backup-db.sh` — Database backup script
- `docker/scripts/setup-ec2.sh` — AWS EC2 instance setup script (Docker, Docker Compose, firewall)

**Nginx Configuration (all new)**
- `nginx/nginx.conf` — Main Nginx config (worker processes, gzip, logging)
- `nginx/conf.d/default.conf` — Server block with Laravel API routing, static file serving, CORS headers
- `nginx/ssl_params.conf` — SSL/TLS security parameters

**CI/CD Pipeline (new)**
- `.github/workflows/ci-cd.yml` — GitHub Actions workflow for automated testing and deployment
- Runs on push to `main` and `develop` branches
- Steps: checkout, PHP setup, Composer install, test execution, Docker build, deployment

**Other**
- `.dockerignore` — Excludes unnecessary files from Docker builds
- `.gitignore` — Updated to exclude Docker volumes and environment files
- `api/.env.example` — Updated with Docker-specific database configuration defaults

---

### Documentation

**CLAUDE.md**
- Updated demo user names and example data to reflect new seeder values (Justin Justin, Julian Tabona)
- Updated example external users section with Botswana names matching new demo data
- Updated settings page example to show "Justin Justin" instead of "Justin Sobhee"

**PROPOSAL.md (new)**
- Client-facing project proposal document

---

### Sample Files

**`samples/` directory (new)**
- Added sample documents and receipts for development and testing purposes
