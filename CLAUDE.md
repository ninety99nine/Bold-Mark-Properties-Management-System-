# CLAUDE.md — Bold Mark Properties Management System

> This file is the single source of truth for all AI agents working on this project.
> It must be read in full before writing any code, making any architectural decisions,
> or suggesting any features. Update this file as the project evolves.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [The Client — Bold Mark Properties](#2-the-client--bold-mark-properties)
3. [The Problem We Are Solving](#3-the-problem-we-are-solving)
4. [Competitive Reference — WeConnectU](#4-competitive-reference--weconnectu)
5. [Our System — What We Are Building](#5-our-system--what-we-are-building)
6. [SaaS Business Model](#6-saas-business-model)
7. [Tech Stack](#7-tech-stack)
8. [Architecture Overview](#8-architecture-overview)
9. [Roles & Permissions Model](#9-roles--permissions-model)
10. [Feature Modules — Detailed Spec](#10-feature-modules--detailed-spec)
11. [Design & Brand Guidelines](#11-design--brand-guidelines)
12. [Development Roadmap & Checklist](#12-development-roadmap--checklist)
13. [MCP & AI Agent Integration](#13-mcp--ai-agent-integration)
14. [Environment & Infrastructure](#14-environment--infrastructure)
15. [Project Context & Constraints](#15-project-context--constraints)
16. [Key Contacts & Resources](#16-key-contacts--resources)

---

## 1. Project Overview

**Project Name:** Bold Mark Properties Management System (working title: "BoldMark PMS")
**Type:** Multi-tenant SaaS Property Management Platform
**Developer:** Optimum Quality (Julian Tabona)
**Primary Client:** Bold Mark Properties (Johannesburg & Botswana)
**Date Started:** March 2026
**Repository:** GitHub (TBD — to be created under Optimum Quality organisation)

### What Is This System?

A modern, full-stack property management platform purpose-built for South African Body Corporate and Homeowners Association (HOA) managing agents. It is designed to:

1. Replace and surpass WeConnectU for Bold Mark Properties' day-to-day operations
2. Function as a white-labelled/multi-tenant SaaS platform that Bold Mark can resell to other property management companies

The system manages the full lifecycle of community scheme administration: financials, levy billing, debt management, compliance, maintenance, communications, document management, meetings, and owner/trustee portals.

---

## 2. The Client — Bold Mark Properties

**Company:** Bold Mark Properties
**Website:** www.boldmarkprop.co.za
**Tagline:** "Moving People Forward"
**Mission:** Remove the stress from the daily running of community schemes. Maintain and manage properties with honesty, transparency and passion.
**Registrations:** NAMA-9141 | PPRA Registered: 202603011001590
**Offices:** Johannesburg (112 Boeing Rd, Bedfordview) | Botswana (The Office, Fairgrounds, Gaborone)

### Services They Offer

| Service | Description |
|---|---|
| Property Sales & Leasing | Full support from first-time buyers to seasoned investors |
| Financial Services | Transparent accounting, budgeting, comprehensive reporting |
| Property Management | Turnkey solutions for sectional title and HOA communities |
| Consulting Services | Expert real estate advice for property projects |

### Brand Values

- **100% Transparent** — No hidden fees, no surprises
- **Personal & Honest** — Built on trust
- **Cutting Edge Technology** — Live data, always at fingertips
- **Comprehensive Reporting** — Monthly reports trustees can act on
- **Value for Money** — Premium service at fair rates

### Current Pricing Model

R21,000/month (excl. VAT) for up to 200 units — full management package including:
- Admin & Secretarial
- Billing & Collections
- Financial & Compliance
- Online Trustee Portal
- Job Tracking CRM
- Property Management & Operations

### Management Team Structure Per Scheme

Each community scheme managed by Bold Mark has a dedicated team of three:
- **Portfolio Manager** — primary contact, attends director meetings, ensures compliance
- **Financial Controller** — financial reporting, budgets, levy collections, audit readiness
- **Portfolio Assistant** — day-to-day operations, communication, contractor coordination

### Current Clients

- Lyndhurst Estate (Crystal Mews Body Corporate)
- Crystal Mews BC (Bramley View)
- King Arthur BC (Florida)
- And many more across Johannesburg and Botswana

---

## 3. The Problem We Are Solving

### Pain Points Identified (from discovery call)

1. **Repetitive daily tasks** — the Bold Mark team has complained about repetitive workflows. The system must reduce manual, repetitive work through automation and smart defaults.
2. **Fragmented tools** — currently using WeConnectU which, while functional, has UX limitations and gaps that the client wants addressed.
3. **No brand ownership** — using a third-party platform means they present someone else's interface to their clients. Our system gives them their own branded platform.
4. **Debt and finance tracking** — this is explicitly called out as a critical priority. The client cares deeply about the depth of financial tracking, levy arrears management, and debt control.
5. **Complexity in daily workflow** — trustees, directors, and the management team find the current system overwhelming. We need to simplify without sacrificing depth.

### What the Client Wants

- A system that does **everything WeConnectU does, but better**
- **Modern, professional, premium design** consistent with their brand identity
- **Reduced repetition** in workflows — automation where possible
- **Depth in finance and debt management** — this is the top priority feature area
- **A platform they can resell** — full SaaS multi-tenancy to onboard other managing agents

---

## 4. Competitive Reference — WeConnectU

> WeConnectU (app.weconnectu.co.za) is the system Bold Mark currently uses.
> We must understand and match all its capabilities, then go beyond them.

WeConnectU is the leading South African property management platform (750,000+ units, 1,800+ clients, 20,000+ property professionals). It offers three integrated products:

### 4.1 Community Management System (CMS) — Primary Reference

The CMS is our core reference. It includes:

#### Financial Management
- Custom double-entry accounting engine built for community schemes
- Separate Admin Fund and Reserve Fund accounting, reporting, and budgeting (STSMA-required)
- Levy billing and levy roll generation
- Trial balance, income/expense statements, cash-flow analysis
- Actual vs. budget reports for both Admin and Reserve Fund books
- Bank statement import and bulk reconciliation (all major SA banks)
- Automated community cashbook reconciliation
- Supplier invoice capture and bulk payment file generation
- Trustee authorization workflow for account payables
- Customer and Supplier Aging Analysis reports
- CSOS levy calculation, raising, and payment-over process
- CSOS annual return preparation and submission forms

#### Arrears & Debt Management
- Automated debit order processing via Netcash integration
- Template-based arrears notices and letters of demand
- Automated administration fee billing when accounts fall into arrears
- Dedicated debt collection module (for registered debt collectors)
- Debtor tracking: all collection notes, stages, and automated fee billing per unit
- Bulk personalized arrears communications (email and SMS)

#### Compliance Management
- Compliance Manager and Calendar (portfolio-wide and community-specific)
- Color-coded compliance status: Compliant (green) vs. Non-compliant (red)
- Portfolio-level compliance dashboard with % compliance per community
- Compliance checklists, document storage, deadline tracking
- STSMA, CSOS Act, and POPIA compliance obligations support

#### Warnings & Penalties
- Issue warnings and penalty letters to residents/owners
- Penalty invoices automatically linked to owner accounts
- Trustee transparent reporting of all warnings and penalties issued

#### Transfers & Clearance Certificates
- Structured 4-step transfer process management
- Automatic generation of clearance/consent certificates on transfer
- Unit billing changes applied on transfer
- Global dashboard for outstanding transfers across all communities

#### Maintenance & Task Management
- Job card creation and tracking
- Task management: create, assign, track across communities
- Global task dashboard across all communities

#### MeetingSpace (Community Meeting Management)
- AGM, SGM, Trustee meetings, Director meetings
- In-person, online (Zoom/Teams), and hybrid formats
- Pre-meeting: bulk invitations, agenda distribution, RSVP management
- Proxy management with weighted voting cards
- Quorum calculation (automated per legislation)
- Electronic attendance register
- Live in-meeting voting with real-time results (PQ-weighted voting)
- Integrated minutes capture
- Post-meeting: automated task creation from resolutions

#### Communications
- Unlimited bulk email and SMS at portfolio and community level
- Targeted messaging to specific groups (all owners, trustees only, debtors, etc.)
- Email archive for audit trail
- Template-based communications with merge fields

#### Document Management
- Cloud-based, unlimited document storage
- Store: minutes, CSOS forms, insurance records, compliance docs, contracts, financials
- Documents shareable directly via email

#### Trustee Portal & App
- iOS Trustee App: view/authorize invoices, track tasks, access documents
- Web Trustee Portal: real-time financial data, bank balances, arrears, reserve fund

#### Owner Portal
- View levy statements and account balance
- Download statements and documents
- Access community notices

#### Dashboards
- Portfolio Dashboard: KPI overview across all communities
- Community Dashboard: bank balances, admin/reserve fund status, compliance, outstanding tasks
- Real-time data (bank data updated every 15 minutes)

### 4.2 WeConnectU's Key Differentiators (To Match or Beat)

| WeConnectU Feature | Our Plan |
|---|---|
| Custom double-entry accounting for community schemes | Build custom levy/fund accounting logic |
| MeetingSpace AGM module with PQ-weighted voting | Phase 2 feature — build after core financials |
| CSOS form submission integration | Must support CSOS levy calculations and reporting |
| 4-step clearance certificate automation | Build structured transfer workflow |
| Dedicated debt collection module | High priority — client's top concern |
| Trustee iOS app | Phase 2 — mobile-responsive web first, then native app |
| Portfolio-wide compliance calendar | Build compliance planner with calendar view |
| RedRabbit offline inspection app | Phase 3 — maintenance/inspection mobile features |

### 4.3 Where We Go Beyond WeConnectU

- **Superior UX/UI** — modern, clean, professional design vs. WeConnectU's dated interface
- **Bold Mark branding** — first-party branded experience for their clients
- **Multi-tenant SaaS with reseller model** — WeConnectU is not resellable by clients
- **AI-native workflows** — automation and smart suggestions from day one
- **Better reporting** — richer, exportable, customizable report builder
- **Better communication tools** — modern notification center, in-app messaging

### 4.4 Competitor Landscape (for benchmarking)

| Platform | Focus | Key Strength |
|---|---|---|
| WeConnectU | Body Corp, HOA, Rentals | Most complete SA platform |
| PayProp | Rental trust accounting | Best payment automation |
| PropWorx | Estate agency (rentals/sales) | Strong inspection module |
| MRI Software SA | Enterprise real estate | IFRS-grade financials |
| Lexpro | Sectional title accounting | STSMA-specific compliance depth |
| PowerProp | Sectional title + rentals | Solid traditional accounting |

---

## 5. Our System — What We Are Building

### System Name

Working title: **BoldMark PMS** (to be decided with client). The SaaS product name may differ.

### Core User Types

1. **Super Admin** — Optimum Quality / System Owner (manages all tenants/companies)
2. **Company Admin** — Managing agent company (e.g., Bold Mark Properties)
3. **Portfolio Manager** — Employee assigned to manage schemes
4. **Financial Controller** — Finance-focused employee per scheme
5. **Portfolio Assistant** — Operations-focused employee
6. **Trustee / Director** — Community scheme trustee/director (external client)
7. **Owner** — Unit owner in a scheme (external client)
8. **Tenant** — Occupant of a unit (future scope)
9. **Contractor** — Maintenance service provider (future scope)

### Module Overview

The system is organized into the following top-level modules:

| Module | Priority | Phase |
|---|---|---|
| Authentication & Multi-tenancy | Critical | 1 |
| Dashboard (Portfolio + Community) | Critical | 1 |
| Communities (Scheme/Complex Management) | Critical | 1 |
| Units & Owners Management | Critical | 1 |
| Levy Billing & Collections | Critical | 1 |
| **Debt Management (Arrears, Collections)** | **Critical** | **1** |
| Financial Accounting (Admin Fund + Reserve Fund) | Critical | 1 |
| Bank Reconciliation | Critical | 1 |
| Compliance Planner | High | 1 |
| Task / Job Card Management | High | 1 |
| Communications (Bulk Email/SMS) | High | 1 |
| Document Management | High | 1 |
| Trustee Portal | High | 2 |
| Owner Portal | Medium | 2 |
| Transfers & Clearance Certificates | Medium | 2 |
| Warnings & Penalties | Medium | 2 |
| Meeting Management (AGM/SGM) | Medium | 2 |
| Reports & Analytics | High | 2 |
| Contractor Management | Low | 3 |
| Maintenance / Inspection Module | Low | 3 |
| SaaS Onboarding / Company Setup | Medium | 2 |
| Billing & Subscription Management | Medium | 2 |

---

## 6. SaaS Business Model

This is NOT just a single-client application. It is a multi-tenant SaaS platform.

### Tenancy Model

```
Super Admin (Optimum Quality)
└── Tenant: Bold Mark Properties (managing agent company)
    ├── Portfolio: Johannesburg
    │   ├── Community: Crystal Mews BC
    │   ├── Community: King Arthur BC
    │   └── ...
    └── Portfolio: Botswana
        └── Community: ...

└── Tenant: Other Managing Agent Company (future reseller client)
    └── ...
```

### Multi-tenancy Rules

- **Complete data isolation** between tenants — no tenant can ever see another tenant's data
- Each tenant has their own branding (logo, primary color, company name)
- Tenant onboarding via admin panel (Super Admin creates new tenant accounts)
- Tenant-level feature flags (allow/disable features per plan tier)
- Each tenant can have unlimited communities, units, and users within their plan limits

### Pricing Tiers (To Be Defined With Client)

Bold Mark will control pricing when they resell to other companies. The platform should support:
- Free trial / onboarding period
- Per-unit pricing or flat monthly fees per tier
- Feature-gated plans (e.g., basic vs. professional vs. enterprise)
- Billing management for tenant subscriptions

---

## 7. Tech Stack

### Backend

| Technology | Version | Purpose |
|---|---|---|
| Laravel | 13.x | API backend, business logic, queue processing |
| PHP | 8.3+ | Runtime |
| Laravel Passport | Latest | OAuth2 API authentication, token management |
| Spatie Laravel Permission | Latest | Role-based access control (RBAC) |
| Supabase | N/A | PostgreSQL database, realtime (DB-level) |
| AWS S3 | N/A | File and document storage |
| Resend | Latest | Transactional email delivery |
| Pusher | Latest | Real-time notifications and live updates |
| Laravel Queues | Built-in | Background jobs (levy generation, bulk email, etc.) |

### Frontend

| Technology | Version | Purpose |
|---|---|---|
| Vue 3 | Latest stable | Frontend SPA framework |
| Vite | Latest | Build tooling and dev server |
| Tailwind CSS | 4.x | Utility-first CSS framework |
| Axios | Latest | HTTP client for API calls |

### Infrastructure & DevOps

| Technology | Purpose |
|---|---|
| Vercel | Frontend deployment (Vue SPA) |
| Supabase | Database hosting (PostgreSQL) |
| AWS S3 | File and document storage |
| Pusher | Real-time notifications and live events |
| GitHub | Version control, CI/CD |
| Resend | Transactional email delivery |

### Key Laravel Packages (Expected)

- `laravel/passport` — API OAuth2
- `spatie/laravel-permission` — roles & permissions
- `spatie/laravel-media-library` — document/file management
- `maatwebsite/excel` — Excel/CSV import and export
- `barryvdh/laravel-dompdf` or `spatie/browsershot` — PDF generation (statements, reports)
- `laravel/horizon` — queue monitoring
- `resend/laravel` — transactional email via Resend
- `league/flysystem-aws-s3-v3` — AWS S3 file storage
- `laravel/reverb` or `pusher/pusher-php-server` — real-time broadcasting via Pusher
- `pestphp/pest` + `pestphp/pest-plugin-laravel` — testing framework (replaces PHPUnit)

### Key Vue 3 Packages (Expected)

- `vue-router` — client-side routing
- `pinia` — state management
- `@vueuse/core` — utility composables
- `axios` — HTTP calls
- `laravel-echo` + `pusher-js` — real-time notifications (Pusher client)
- Chart library (e.g., `chart.js` with `vue-chartjs`, or `apexcharts`) — financial dashboards
- `@headlessui/vue` or similar — accessible UI components
- Date library (`date-fns` or `dayjs`) — date manipulation

### Testing Strategy (PEST)

All backend tests use **PEST** (Laravel PEST plugin). PHPUnit is not used directly.

#### Test Types

| Type | Location | Purpose |
|---|---|---|
| Unit | `tests/Unit/` | Pure logic — levy calculations, debt aging, PQ apportionment, financial formulas |
| Feature | `tests/Feature/` | Full API endpoint tests — HTTP requests, auth, responses, DB state |
| Integration | `tests/Feature/Integration/` | Multi-tenancy isolation — ensure tenant A cannot access tenant B data |

#### Key Testing Rules

- Every API endpoint must have a corresponding feature test
- All financial calculation logic must have unit tests (levy generation, aging, interest)
- Multi-tenancy isolation must be tested explicitly — this is a critical security requirement
- Use PEST's `actingAs()` with different role fixtures for permission boundary testing
- Tests run against a dedicated test database (separate from dev Supabase project)
- Use `RefreshDatabase` trait on all feature tests

#### Test Naming Convention

```
it('calculates levy arrears correctly for 90-day overdue account')
it('prevents portfolio manager from accessing another tenant community')
it('generates correct levy roll for 47-unit community')
```

### Seeding Strategy

Seeds are split into two categories: **system seeds** (always run) and **demo seeds** (development/staging only).

#### System Seeds (always run — `db:seed`)

| Seeder | Purpose |
|---|---|
| `RolesAndPermissionsSeeder` | Create all Spatie roles and permissions |
| `SuperAdminSeeder` | Create the Optimum Quality super admin account |

#### Demo Seeds (`--class=DemoSeeder` — dev/staging only, never production)

The demo dataset simulates a realistic Bold Mark Properties environment:

| Seeder | Data Created |
|---|---|
| `DemoTenantSeeder` | 1 tenant company: "Bold Mark Properties Demo" |
| `DemoUsersSeeder` | Portfolio manager, financial controller, portfolio assistant accounts |
| `DemoCommunitiesSeeder` | 3 communities: Crystal Mews BC (47 units), King Arthur BC (32 units), Lyndhurst Estate (28 units) |
| `DemoUnitsAndOwnersSeeder` | Full unit roster per community, owner accounts with SA names and realistic data |
| `DemoTrusteesSeeder` | 3 trustee accounts per community (linked to owner records) |
| `DemoLevySeeder` | 6 months of levy history — mix of paid-up, partial, and arrear accounts |
| `DemoArrearsSeeder` | Varied arrear stages: 10% current, 15% in 30-day, 10% in 60-day, 5% in 90+ day |
| `DemoFinancialSeeder` | Admin Fund + Reserve Fund trial balance, budget setup, sample supplier invoices |
| `DemoComplianceSeeder` | Compliance calendar with mix of compliant and overdue items |
| `DemoTasksSeeder` | Open job cards and maintenance tasks across communities |
| `DemoDocumentsSeeder` | Sample meeting minutes, CSOS forms, insurance records (placeholder files) |
| `DemoCommunicationsSeeder` | Email log history with realistic bulk send records |

#### Demo Account Credentials (consistent across resets)

| Role | Email | Password |
|---|---|---|
| Super Admin | `super@demo.boldmark.test` | `password` |
| Company Admin | `admin@demo.boldmark.test` | `password` |
| Portfolio Manager | `pm@demo.boldmark.test` | `password` |
| Financial Controller | `fc@demo.boldmark.test` | `password` |
| Portfolio Assistant | `pa@demo.boldmark.test` | `password` |
| Trustee | `trustee@demo.boldmark.test` | `password` |
| Owner | `owner@demo.boldmark.test` | `password` |

---

## 8. Architecture Overview

```
┌─────────────────────────────────────────────────┐
│               Vue 3 SPA (Vite)                  │
│         Deployed on Vercel                       │
│  [Admin Portal] [Trustee Portal] [Owner Portal]  │
└──────────────────┬──────────────────────────────┘
                   │ HTTPS / Axios
                   ▼
┌─────────────────────────────────────────────────┐
│         Laravel 13 API (REST)                   │
│    Laravel Passport (OAuth2 token auth)          │
│    Spatie Permissions (RBAC)                     │
│    Multi-tenancy middleware                      │
│    Queue workers (jobs, notifications)           │
└──────────┬───────────────────┬───────────────────┘
           │                   │
           ▼                   ▼
┌──────────────────┐  ┌──────────────┐  ┌─────────────┐
│   Supabase       │  │   AWS S3     │  │   Pusher    │
│   PostgreSQL DB  │  │   Documents  │  │   Realtime  │
└──────────────────┘  │   Files      │  └─────────────┘
                      └──────────────┘
                      ┌──────────────┐
                      │   Resend     │
                      │   Email      │
                      └──────────────┘
```

### Multi-tenancy Implementation

- Use **tenant_id** column on all tenant-scoped models
- Global scope middleware to automatically filter queries by authenticated tenant
- Tenant resolution via subdomain (e.g., `boldmark.oursystem.com`) or API header
- Separate storage paths per tenant in Supabase Storage

### API Design

- RESTful API design
- API versioning: `/api/v1/...`
- JSON responses with consistent envelope: `{ data, meta, errors }`
- Laravel Passport personal access tokens or OAuth2 for external integrations
- Rate limiting per tenant

---

## 9. Roles & Permissions Model

Using Spatie Laravel Permission with the following role hierarchy:

### System-Level Roles

| Role | Scope | Description |
|---|---|---|
| `super-admin` | Global | Optimum Quality — full system access |
| `company-admin` | Tenant | Managing agent company administrator |
| `portfolio-manager` | Tenant | Manages assigned communities |
| `financial-controller` | Tenant | Finance-focused access |
| `portfolio-assistant` | Tenant | Operational access |

### Community-Level Roles (External Clients)

| Role | Scope | Description |
|---|---|---|
| `trustee` | Community | Trustee/director of a specific scheme |
| `owner` | Unit | Unit owner — view own account |
| `tenant` | Unit | Occupant (future) |
| `contractor` | System | Maintenance provider (future) |

### Key Permissions (Non-exhaustive)

- `view-financials`, `manage-financials`
- `view-levies`, `manage-levies`, `approve-levies`
- `view-debt`, `manage-debt`, `approve-debt-actions`
- `view-compliance`, `manage-compliance`
- `view-maintenance`, `manage-maintenance`, `assign-contractors`
- `manage-users`, `manage-communities`, `manage-tenants`
- `view-reports`, `export-reports`
- `send-communications`
- `manage-documents`
- `approve-payments` (trustee permission)

---

## 10. Feature Modules — Detailed Spec

### 10.1 Authentication & Multi-tenancy

- Laravel Passport OAuth2 with personal access tokens
- Email/password login
- Password reset via Resend
- Remember me / session management
- Tenant resolution (subdomain or header-based)
- Per-tenant user management
- Invite-based user onboarding (email invite via Resend)
- 2FA (future)

---

### 10.2 Dashboard

#### Portfolio Dashboard (Company Admin / Portfolio Manager view)

Shows across ALL managed communities:

- Total communities managed
- Total units under management
- **Total arrears (ZAR)** — highlighted, top-level KPI
- **Total debt across portfolio** — critical client requirement
- Compliance status: % compliant communities
- Open job cards / tasks
- Communities with critical compliance issues (red flags)
- Upcoming AGM/meeting dates
- Recent activity feed

#### Community Dashboard (per scheme)

- Bank balance (Admin Fund + Reserve Fund)
- **Total outstanding levies / arrears** — prominent display
- **Customer Control Balance** (debtors total)
- **Supplier Control Balance** (creditors total)
- Investment account balance
- Deficit indicator
- Compliance status (color-coded)
- Open tasks / job cards count
- Reminders sent / payment arrangements / handed to attorneys
- Recent transactions
- Planner: upcoming events/tasks

---

### 10.3 Communities (Scheme Management)

Each "Community" represents one Body Corporate or HOA scheme.

**Community record contains:**
- Scheme name, type (Body Corporate / HOA / Estate)
- Registration numbers (CSOS, company registration)
- Physical address
- Financial year end date
- Admin Fund and Reserve Fund separation
- CSOS levy rate
- AGM date / next AGM due date
- Insurance details and renewal date
- 10-year maintenance plan tracking
- Assigned Portfolio Manager, Financial Controller, Portfolio Assistant
- Assigned Trustees/Directors
- Document library (per community)
- Communication history

---

### 10.4 Units & Owners Management

**Unit record contains:**
- Unit number / stand number
- Participation Quota (PQ) — used for levy apportionment and voting
- Owner details: name, ID/company number, email, phone, postal address
- Tenant details (if applicable)
- Current levy amount (Admin Fund + Reserve Fund + CSOS)
- Account balance and statement
- Linked warnings and penalties
- Linked job cards / maintenance history
- Communication history per unit
- Transfer history

**Owner record:**
- Personal/entity details
- All units owned (may own multiple units in one or more schemes)
- Account statements
- Portal login credentials

---

### 10.5 Levy Billing & Collections

**CRITICAL PRIORITY MODULE**

- Levy roll: generate monthly levy invoices per unit
- Apportionment by PQ or fixed amount
- Admin Fund levy + Reserve Fund levy + CSOS levy + special levies
- Bulk levy raise (raise invoices for all units in one action)
- Individual levy adjustments
- Special levy creation and billing
- Pro-rata billing on unit transfers
- Automated recurring billing (scheduled monthly)
- Payment allocation against outstanding invoices (FIFO or custom)
- Statement generation (per unit, downloadable PDF)
- Bulk statement distribution via Resend

**Collections:**
- Record payments against unit accounts
- Bank import and auto-matching to unit accounts
- Manual payment capture
- Batch receipting
- Payment allocation rules

---

### 10.6 Debt Management (Arrears & Collections)

**THIS IS THE HIGHEST PRIORITY FEATURE MODULE.**
The client has explicitly flagged debt/finances as their primary concern.

#### Arrears Tracking

- Real-time arrears balance per unit, per community, and portfolio-wide
- Aging analysis: current, 30 days, 60 days, 90+ days
- Age analysis report (exportable to PDF and Excel)
- Automated arrears status flag on unit accounts

#### Debt Collection Workflow

Multi-stage collection process per debtor:

1. **Reminder** — automated friendly reminder (configurable days after due date)
2. **First Letter of Demand** — formal letter with outstanding amount
3. **Second Letter of Demand** — escalated notice
4. **Hand Over to Attorney** — flag for legal action, track attorney reference
5. **Payment Arrangement** — record agreed payment plan, track adherence
6. **Write Off** — formal write-off with approval workflow

Each stage:
- Tracked with timestamps and responsible agent
- All communication sent via Resend and logged
- Notes captured per debtor interaction

#### Automated Administration Fees

- Billing of admin fees when account falls into arrears (configurable per community)
- Interest calculation on overdue amounts (configurable rate)
- All fees auto-generated and added to unit account

#### Payment Arrangements

- Record payment arrangements with schedule
- Track adherence vs. arrangement
- Automated reminders for upcoming arrangement payments
- Flag breached arrangements

#### Debt Dashboard (Portfolio-wide)

- Total outstanding per stage (reminder, first demand, second demand, attorney, arrangement)
- Debtors list with balance and stage
- Trending chart (arrears growing or shrinking)
- % of levy book in arrears
- Collections recovered this month/quarter/year

#### Letters & Templates

- Configurable per-community letter templates
- Merge fields: owner name, unit number, balance, due dates, amount, company details
- PDF generation for letters of demand
- Bulk dispatch via Resend

---

### 10.7 Financial Accounting

South African community scheme accounting requires:
- **Separate Admin Fund** and **Reserve Fund** books
- Double-entry accounting
- Trial balance
- Income and expenditure statement
- Cash flow report
- Budget vs. Actual comparison

**Chart of Accounts:**
- Pre-built SA community scheme chart of accounts
- Admin Fund: levy income, maintenance expense, insurance, management fees, admin costs
- Reserve Fund: interest income, capital expenditure
- CSOS levy account

**Supplier (Creditor) Management:**
- Capture supplier invoices
- Allocate to relevant fund (Admin or Reserve)
- Approval workflow (trustee authorization)
- Bulk payment file generation (EFT batch file)
- Supplier statements and age analysis

**Bank Accounts:**
- Multiple bank accounts per community (Admin, Reserve, CSOS)
- Bank statement import (CSV from all major SA banks)
- Manual transaction capture
- Bank reconciliation interface (match statement lines to transactions)

**Budgeting:**
- Annual budget per community (Admin Fund + Reserve Fund)
- Budget vs. actual real-time comparison
- Budget import from Excel
- Budget report generation

**CSOS:**
- CSOS levy calculation (based on admin levy amounts)
- Monthly CSOS levy raising and collection
- CSOS payment processing
- CSOS annual return documentation support

---

### 10.8 Bank Reconciliation

- Import bank statements (CSV/Excel) for all major SA banks
- Auto-match statement lines to system transactions
- Manual matching interface for unmatched items
- Flag and investigate exceptions
- Mark reconciliation as complete (with timestamp and responsible person)
- Reconciliation report
- Cashbook balance vs. bank statement balance

---

### 10.9 Compliance Planner

- Compliance calendar: view upcoming tasks/deadlines by community or portfolio-wide
- Compliance items: AGM date, audit deadline, CSOS return, insurance renewal, trustee meeting dates, budget meeting, POPIA obligations
- Status per item: Compliant / Due Soon / Non-compliant / Action Required
- Color-coded portfolio compliance dashboard
- Automated reminders for approaching deadlines
- Attach documents to compliance items (e.g., uploaded AGM minutes)
- Compliance report generation (monthly)
- Global compliance percentage KPI on dashboard

---

### 10.10 Task / Job Card Management

- Create tasks linked to a community, unit, or contractor
- Task types: maintenance, administrative, compliance, owner query, general
- Priority levels: low, medium, high, urgent
- Status: open, in progress, awaiting response, closed
- Assign to team members
- Due date with overdue flags
- Task comments (internal notes + external communications)
- File attachments per task
- 24-hour response time SLA tracking
- Portfolio-wide open tasks dashboard
- Task history per unit (full audit trail)

---

### 10.11 Communications

- **Bulk Email** via Resend to:
  - All owners in a community
  - All trustees in a community
  - All debtors in a community
  - Specific unit owners (selected list)
  - Portfolio-wide announcements (to all communities of a tenant)
- **Email Templates** with merge fields (owner name, unit, community, balance, etc.)
- **Email archive** — all emails sent stored and viewable per community and per unit
- **SMS support** (via third-party SMS gateway — to be defined)
- **Notification center** — in-app notifications for tasks, reminders, system events
- **Communication log** per unit — see all comms history in unit record

---

### 10.12 Document Management

- Upload and store documents per community, per unit, or globally
- Document categories: Meeting Minutes, CSOS Documents, Insurance, Financial Statements, Contracts, Compliance, Correspondence
- Secure access control (role-based document visibility)
- Document sharing via email (direct from system)
- Version management (latest version flagged, history kept)
- Bulk upload
- Preview in-browser (PDF, images)
- Download

---

### 10.13 Trustee Portal

Dedicated portal for trustees and directors of a specific community scheme.

- Login via secure link / password
- Real-time financial overview: bank balance, Admin Fund, Reserve Fund, arrears total
- Payment approval workflow: review and approve supplier payment requests
- Task dashboard: view assigned tasks, add comments, update status
- Document access: read community documents and meeting minutes
- Compliance status overview for their community
- Meeting schedule and event calendar
- Planner: view and track items like AGM pack sent, audit status, budget circulated
- Community announcements

---

### 10.14 Owner Portal

Dedicated portal for unit owners.

- Secure login (invite via email)
- View current account balance and outstanding amount
- View and download levy statements (PDF)
- View community notices and announcements
- Submit maintenance requests / queries (logged as tasks)
- View community documents (publicly accessible ones)
- View outstanding payments and make payment (future — payment gateway)

---

### 10.15 Transfers & Clearance Certificates

Structured workflow for unit transfers (ownership changes):

**4-Step Process:**
1. **Initiate Transfer** — record transfer date, buyer/seller details, conveyancer details
2. **Account Settlement** — confirm all arrears are settled or payment arrangement made
3. **Generate Clearance Certificate** — auto-generate PDF clearance/consent certificate with all required fields
4. **Complete Transfer** — update unit ownership, apply pro-rata levy billing

- Track all outstanding transfers globally across all communities
- Notify relevant parties (conveyancer, incoming owner) via Resend
- Document storage for each transfer file

---

### 10.16 Warnings & Penalties

- Issue formal warnings to unit owners/occupants for conduct rule breaches
- Warning types configured per community (based on conduct rules)
- Escalation steps: verbal warning → written warning → fine
- Penalty invoice auto-generated and linked to owner account
- All warnings tracked per unit with full history
- Aligned to CSOS Act and conduct rule enforcement requirements
- Report: all outstanding warnings and fines

---

### 10.17 Meeting Management (AGM / SGM / Trustee Meetings)

- Create meetings: AGM, SGM, Trustee meeting, Director meeting
- Meeting formats: in-person, virtual (Zoom/Teams link), hybrid
- **Pre-meeting:**
  - Bulk invitation dispatch (email + SMS) to owners/trustees
  - RSVP management
  - Proxy form distribution and collection
  - Meeting pack document attachment (agenda, financial statements, proposed budget, etc.)
  - Quorum calculation (automated based on PQ)
- **During meeting:**
  - Attendance register (digital check-in)
  - Proxy holder management
  - Voting module (resolutions with live results)
  - PQ-weighted voting support
  - Minutes capture
- **Post-meeting:**
  - Auto-generate task list from resolutions
  - Minutes storage and distribution
  - Meeting report generation

---

### 10.18 Reports & Analytics

**Standard Reports:**

| Report | Type |
|---|---|
| Age Analysis (Debtors) | Financial |
| Trial Balance | Financial |
| Income & Expenditure Statement | Financial |
| Budget vs. Actual | Financial |
| Cash Flow Statement | Financial |
| Levy Roll | Financial |
| Bank Reconciliation Report | Financial |
| Compliance Report | Compliance |
| Task Completion Report | Operations |
| Arrears Summary (per community + portfolio) | Financial |
| Trustee Monthly Report | Financial |
| Annual Financial Statements (draft) | Financial |

- All reports exportable to PDF (via DomPDF/Browsershot) and Excel
- Date range filters on all financial reports
- Report delivery via Resend (scheduled monthly reports)
- Report builder (future — custom configurable reports)

---

### 10.19 SaaS — Company Onboarding & Management

- Super Admin panel: manage all tenant companies
- Create new tenant company account
- Assign plan/tier to tenant
- Enable/disable features per tenant
- View per-tenant usage statistics
- Tenant branding: upload logo, set primary color
- Billing and subscription management per tenant (future)
- Tenant admin invites new team members

---

## 11. Design & Brand Guidelines

### Bold Mark Properties Brand Colors

| Color | Hex | Usage |
|---|---|---|
| Navy Blue (Primary) | `#1a2744` | Backgrounds, headers, primary UI elements |
| Gold/Orange (Accent) | `#e8a040` | CTAs, highlights, active states, accent lines |
| White | `#ffffff` | Card backgrounds, content areas |
| Light Gray | `#f5f6f8` | Page backgrounds, table alternating rows |
| Success Green | `#22c55e` | Compliant status, positive amounts |
| Warning Red | `#ef4444` | Non-compliant status, arrears, deficits |

### Design Principles

1. **Professional and premium** — this is not a budget tool. Every screen should feel polished and trustworthy. Bold Mark's clients are property owners and trustees who expect professionalism.
2. **Information density with clarity** — financial data is complex; present it clearly without overwhelming. Use cards, clean tables, clear hierarchy.
3. **Consistent typography** — use a professional sans-serif pairing. Consider a serif display font for headings (as Bold Mark uses in their collateral).
4. **Mobile-responsive** — trustees may access the portal on mobile. The management portal should work on tablets. Owner portal must be mobile-first.
5. **Purposeful use of color** — red/amber/green for status indicators (arrears, compliance). Never use color for decoration only.
6. **White space** — avoid clutter. Let data breathe.
7. **Action-oriented UI** — surfaces what the user needs to act on. Surfacing overdue items, pending approvals, and critical alerts prominently.

### UI Component Guidelines

- Use Tailwind 4 utility classes — no custom CSS unless absolutely necessary
- Build a consistent component library (buttons, cards, badges, modals, tables, forms)
- Tables for financial data must have sortable columns and be exportable
- All monetary amounts displayed in ZAR with `R` prefix, formatted with commas: `R 141,666.64`
- Negative amounts in red
- Dates displayed in South African format: `21 January 2021` or `21/01/2021`

---

## 12. Development Roadmap & Checklist

> Phases are ordered by business priority. Phase 1 focuses on what the Bold Mark team
> uses every single day. Phases 2+ expand toward full WeConnectU parity and beyond.

### Phase 1 — Core Operations (Daily Workflow Essentials)

The goal of Phase 1 is to make the Bold Mark team's daily work faster and less repetitive.

#### Foundation

- [ ] Laravel 13 project setup with Supabase (PostgreSQL) connection
- [ ] Vue 3 + Vite + Tailwind 4 frontend project setup
- [ ] PEST test suite setup with Laravel plugin
- [ ] Multi-tenancy architecture and middleware
- [ ] Multi-tenancy isolation tests (tenant A cannot access tenant B)
- [ ] Laravel Passport OAuth2 authentication
- [ ] Spatie Permissions — role and permission seeding (`RolesAndPermissionsSeeder`)
- [ ] Base layout components (sidebar, topbar, navigation)
- [ ] User management (invite, create, assign roles)
- [ ] Company settings (name, logo, colors)
- [ ] Resend integration and base email templates
- [ ] Demo seed dataset (`DemoSeeder`) — full realistic Bold Mark environment

#### Communities & Units

- [ ] Community CRUD (create, list, view, edit, archive)
- [ ] Community detail page with all linked data
- [ ] Unit CRUD per community
- [ ] Owner CRUD linked to units
- [ ] PQ (Participation Quota) assignment per unit
- [ ] Owner search and lookup

#### Levy Billing

- [ ] Chart of accounts setup (Admin Fund, Reserve Fund)
- [ ] Levy configuration per community (Admin Fund + Reserve Fund + CSOS rates)
- [ ] Monthly levy roll generation (bulk raise invoices for all units)
- [ ] Individual levy adjustments and special levies
- [ ] Payment capture (manual + bulk)
- [ ] Unit account statement (PDF export)
- [ ] Bulk statement distribution (Resend)

#### Debt Management (HIGH PRIORITY)

- [ ] Arrears calculation and real-time balance tracking per unit
- [ ] Aging analysis report (30/60/90/120+ days)
- [ ] Collection stage workflow (reminder → demand 1 → demand 2 → attorney → arrangement)
- [ ] Letter of demand templates and PDF generation
- [ ] Automated admin fee and interest billing
- [ ] Payment arrangement recording and tracking
- [ ] Bulk arrears communications (Resend email)
- [ ] Debt dashboard (portfolio-wide and per community)
- [ ] Hand-over to attorney tracking with notes

#### Financial Accounting (Core)

- [ ] Double-entry accounting engine (Admin Fund + Reserve Fund)
- [ ] Supplier invoice capture
- [ ] Expense allocation to budget categories
- [ ] Trial balance report
- [ ] Income & Expenditure statement
- [ ] Budget setup per community (annual)
- [ ] Budget vs. Actual report

#### Bank Reconciliation

- [ ] Bank account setup per community
- [ ] Bank statement CSV import (FNB, ABSA, Standard Bank, Nedbank, Capitec formats)
- [ ] Auto-match statement lines to transactions
- [ ] Manual matching interface
- [ ] Reconciliation completion and report

#### Dashboard

- [ ] Portfolio dashboard with arrears KPIs, compliance summary, open tasks
- [ ] Community dashboard with fund balances, arrears, planner

#### Compliance Planner

- [ ] Compliance item setup (AGM, audit, CSOS, insurance, budget meeting)
- [ ] Calendar view
- [ ] Status tracking (compliant / due / overdue)
- [ ] Portfolio compliance percentage KPI

#### Task Management

- [ ] Task CRUD with assignment, priority, due date
- [ ] Task status workflow (open → in progress → closed)
- [ ] Task comments
- [ ] Per-unit task history
- [ ] Portfolio task dashboard

#### Communications

- [ ] Email template builder (with merge fields)
- [ ] Bulk email send to community owners
- [ ] Email log per community and per unit
- [ ] Resend webhook for delivery status

#### Document Management

- [ ] Document upload (AWS S3)
- [ ] Document categories and tagging
- [ ] Document list per community
- [ ] Secure download links

---

### Phase 2 — Portals, Compliance & Full Financial Suite

#### Trustee Portal

- [ ] Trustee login and authentication
- [ ] Real-time financial dashboard
- [ ] Payment approval workflow
- [ ] Task visibility and comments
- [ ] Document access

#### Owner Portal

- [ ] Owner login
- [ ] Account balance and statement view
- [ ] Document downloads
- [ ] Submit query/maintenance request

#### Transfers & Clearance Certificates

- [ ] Transfer workflow (4 steps)
- [ ] Clearance certificate PDF generation
- [ ] Global transfers dashboard

#### Warnings & Penalties

- [ ] Warning creation and escalation workflow
- [ ] Penalty invoice auto-generation
- [ ] Warnings log per unit

#### Meeting Management

- [ ] Meeting creation and configuration
- [ ] Invitation dispatch (Resend)
- [ ] RSVP tracking
- [ ] Proxy management
- [ ] Attendance register
- [ ] In-meeting voting module
- [ ] Minutes capture
- [ ] Post-meeting task generation

#### Reports & Analytics

- [ ] Report builder (predefined report templates)
- [ ] PDF export for all reports
- [ ] Excel export for financial data
- [ ] Scheduled monthly report delivery (Resend)

#### SaaS Onboarding

- [ ] Super Admin panel
- [ ] Tenant company creation
- [ ] Tenant plan/feature management
- [ ] Tenant branding configuration

---

### Phase 3 — Advanced Features & Expansion

- [ ] Mobile-optimized Trustee App (PWA or native)
- [ ] Maintenance / Inspection module
- [ ] Contractor portal and job card management
- [ ] AI-assisted debt collection recommendations
- [ ] Advanced analytics and trend reporting
- [ ] CSOS direct submission integration
- [ ] Debit order processing integration (Netcash or similar)
- [ ] Payment gateway for online levy payments
- [ ] SMS gateway integration (full two-way SMS)
- [ ] API access for third-party integrations
- [ ] Rental management module (expand beyond HOA/BC)

---

## 13. MCP & AI Agent Integration

### Overview

This project uses MCP (Model Context Protocol) to give AI agents deep operational control over the development environment, enabling autonomous coding, database management, deployment, and more.

### Active MCP Servers

All servers below are fully configured and active. Agents working in Claude Code or Cursor have access to all of them.

| MCP Server | Package / Integration | Purpose | Available In |
|---|---|---|---|
| `context7` | `@upstash/context7-mcp` | Live library docs (Laravel, Vue, Tailwind, Supabase, etc.) | Claude Code + Cursor |
| `github` | `@modelcontextprotocol/server-github` | Repo management, PRs, issues, GitHub Actions | Claude Code + Cursor |
| `supabase` | `@supabase/mcp-server-supabase` | Direct DB access, migrations, schema management | Claude Code + Cursor |
| `resend` | `resend-mcp` | Transactional email — send, manage templates, view logs | Claude Code + Cursor |
| `vercel` | Vercel (claude.ai integration) | Deploy, inspect logs, manage projects and domains | Claude Code only |
| `gmail` | Gmail (claude.ai integration) | Email communication and log review | Claude Code only |
| `google-calendar` | Google Calendar (claude.ai integration) | Deadline and meeting tracking | Claude Code only |
| `notion` | Notion (claude.ai integration) | Project documentation and knowledge base | Claude Code only |
| `canva` | Canva (claude.ai integration) | Design assets and branding | Claude Code only |

> **Config locations:**
> - Claude Code global: `~/.claude.json` (under project mcpServers)
> - Cursor global: `~/.cursor/mcp.json`
> - claude.ai integrations are only available in Claude Code, not Cursor

### AI Agent Instructions

When building this system, AI agents should:

1. **Always read this file first** before starting any task
2. **Use context7** to look up current documentation for any library before using it (Laravel 13, Vue 3, Tailwind 4, Supabase, etc.)
3. **Check the tech stack section** to confirm package choices before adding new dependencies
4. **Design for multi-tenancy from day one** — every database model must consider tenant isolation
5. **Prioritize the debt management module** — this is the client's most critical requirement
6. **Follow the brand guidelines** when building UI components
7. **Never hardcode tenant-specific data** — all data must be parameterized by tenant
8. **Always check the roadmap checklist** to understand what phase a feature belongs to

### Environment Variables Required

The following must be set in `.env` files (never commit to git) when building the application.
MCP tokens live in agent config files — do not duplicate them in `.env`.

| Service | .env Variable | Status |
|---|---|---|
| Supabase URL | `SUPABASE_URL` | Pending — get from Supabase project dashboard |
| Supabase Anon Key | `SUPABASE_ANON_KEY` | Pending — get from Supabase project dashboard |
| Supabase Service Key | `SUPABASE_SERVICE_ROLE_KEY` | Pending — get from Supabase project dashboard |
| AWS Access Key ID | `AWS_ACCESS_KEY_ID` | Pending — get from AWS IAM console |
| AWS Secret Access Key | `AWS_SECRET_ACCESS_KEY` | Pending — get from AWS IAM console |
| AWS S3 Bucket | `AWS_BUCKET` | Pending — create bucket in AWS S3 |
| AWS Region | `AWS_DEFAULT_REGION` | Pending — e.g. `af-south-1` (Cape Town) or `eu-west-1` |
| Pusher App ID | `PUSHER_APP_ID` | Pending — get from Pusher dashboard |
| Pusher App Key | `PUSHER_APP_KEY` | Pending — get from Pusher dashboard |
| Pusher App Secret | `PUSHER_APP_SECRET` | Pending — get from Pusher dashboard |
| Pusher Cluster | `PUSHER_APP_CLUSTER` | Pending — e.g. `ap2` (South Africa nearest: `eu`) |
| Resend API Key | `RESEND_API_KEY` | MCP active — use same key in app `.env` |
| GitHub Token | `GITHUB_TOKEN` | MCP active — separate app token if needed |
| Vercel Token | `VERCEL_TOKEN` | MCP active via claude.ai |

---

## 14. Environment & Infrastructure

### Local Development

- **OS:** macOS (Darwin 25.0.0)
- **Shell:** zsh
- **IDE:** Cursor (with Claude MCP integration)
- **Node.js:** Latest LTS
- **PHP:** 8.3+
- **Package Manager:** Bun (frontend), Composer (backend)

### File Paths

| Path | Contents |
|---|---|
| `/Users/juliantabona/Sites/bold-mark-properties-system/` | This project (system) |
| `/Users/juliantabona/Sites/bold-mark-properties/` | Marketing website source (existing) |
| `/Users/juliantabona/Downloads/BoldMark Company Profile.pdf` | Client company profile |
| `/Users/juliantabona/Downloads/BoldMark.pdf` | Additional client material |

### Project Structure (Target)

```
bold-mark-properties-system/
├── api/                    # Laravel 13 backend
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Jobs/
│   │   └── ...
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/api.php
│   └── ...
├── web/                    # Vue 3 frontend
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── composables/
│   │   ├── stores/        # Pinia stores
│   │   ├── router/
│   │   └── ...
│   ├── vite.config.ts
│   └── ...
├── CLAUDE.md               # This file
└── .gitignore
```

### Deployment

- **Frontend (Vue):** Vercel — connect GitHub repo, auto-deploy on merge to main
- **Backend (Laravel):** TBD — options: Vercel Serverless (PHP runtime), Laravel Forge on a VPS, or Railway.app
- **Database:** Supabase (managed PostgreSQL)
- **File Storage:** AWS S3
- **Real-time:** Pusher
- **Email:** Resend

### Git Branching Strategy

- `main` — production branch (always deployable)
- `develop` — integration branch
- `feature/[feature-name]` — individual feature branches
- All features merged via Pull Request with review

---

## 15. Project Context & Constraints

### Engagement Model

- **Service Provider:** Optimum Quality (Julian Tabona)
- **Client:** Bold Mark Properties
- **Approach:** Phased delivery — not a full migration at once. Build progressively, prioritizing daily workflow essentials first.

### Budget & Scope

- Client indicated budget in the **R20,000 range** (Julian flagged that this is a large system and pricing must be careful — do not underprice)
- **Important:** This system will keep growing ("components will keep coming and changing") — scope creep is a known risk. Changes beyond the agreed scope should be quoted separately.
- The quote/proposal was to be sent to the client via WhatsApp/chat shortly after the initial discovery call.

### Timeline (as discussed in discovery call)

- Julian proposed something substantial within 2 weeks of the initial call (March 2026)
- **Phased delivery** is agreed — prioritize Phase 1 features that simplify daily work

### Key Risk: Scope Creep

- This is a complex system. Define scope clearly for each phase.
- Track feature additions and quote additional work separately
- Regular check-ins with Bold Mark Properties to validate priorities

### South African Regulatory Context

The system must account for South African property law and regulations:

- **STSMA (Sectional Titles Schemes Management Act)** — governs body corporate accounting, reserve funds, AGMs, and trustees
- **CSOS Act (Community Schemes Ombud Service Act)** — CSOS levy obligations, dispute resolution, annual returns
- **POPIA (Protection of Personal Information Act)** — data privacy obligations for owner/tenant personal information
- **PPRA (Property Practitioners Regulatory Authority)** — managing agents must be PPRA registered
- **NAMA (National Association of Managing Agents)** — industry body

---

## 16. Key Contacts & Resources

### People

| Person | Role | Contact |
|---|---|---|
| Julian Tabona | Developer (Optimum Quality) | Service provider — project lead |
| Bold Mark Properties Team | Client | www.boldmarkprop.co.za |

### Reference Systems

| System | URL | Notes |
|---|---|---|
| WeConnectU (client's current system) | https://app.weconnectu.co.za | Primary competitive reference |
| Bold Mark Website | http://boldmarkprop.co.za | Client's public website |
| Bold Mark Website Source | /Users/juliantabona/Sites/bold-mark-properties | Existing marketing site code |

### Key Documentation

| Resource | URL |
|---|---|
| Laravel 13 Docs | https://laravel.com/docs |
| Vue 3 Docs | https://vuejs.org/guide |
| Tailwind 4 Docs | https://tailwindcss.com/docs |
| Supabase Docs | https://supabase.com/docs |
| Laravel Passport Docs | https://laravel.com/docs/passport |
| Spatie Permission Docs | https://spatie.be/docs/laravel-permission |
| Vercel Docs | https://vercel.com/docs |
| Resend Docs | https://resend.com/docs |

---

## Changelog

| Date | Change | Author |
|---|---|---|
| 2026-03-30 | Initial CLAUDE.md created | Julian Tabona / Claude Code |

---

> This document is a living specification. Update it as requirements are clarified,
> features are completed, and new information about the client or WeConnectU capabilities
> is discovered. Never let this file become stale — it is the memory of this project.
