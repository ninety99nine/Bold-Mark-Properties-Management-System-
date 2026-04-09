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
5. [Core Design Principles](#5-core-design-principles)
6. [Data Model](#6-data-model)
7. [Phase 1 — MVP Workflow](#7-phase-1--mvp-workflow)
8. [UI Screens — Phase 1](#8-ui-screens--phase-1)
9. [Business Rules](#9-business-rules)
10. [Billing Engine Logic](#10-billing-engine-logic)
11. [Feature Modules — Full System Spec](#11-feature-modules--full-system-spec)
12. [SaaS Business Model](#12-saas-business-model)
13. [User Roles & Permissions](#13-user-roles--permissions)
14. [Tech Stack](#14-tech-stack)
15. [Architecture Overview](#15-architecture-overview)
16. [Design & Brand Guidelines](#16-design--brand-guidelines)
17. [Development Roadmap & Checklist](#17-development-roadmap--checklist)
18. [UX Design Principles](#18-ux-design-principles)
19. [MCP & AI Agent Integration](#19-mcp--ai-agent-integration)
20. [Environment & Infrastructure](#20-environment--infrastructure)
21. [Project Context & Constraints](#21-project-context--constraints)
22. [Glossary](#22-glossary)
23. [Key Contacts & Resources](#23-key-contacts--resources)

---

## 1. Project Overview

**Project Name:** Bold Mark Properties Management System (working title: "BoldMark PMS")
**Type:** Multi-tenant SaaS Property Management Platform
**Developer:** Optimum Quality (Pty) Ltd (Julian Tabona)
**Primary Client:** Bold Mark Properties (Pty) Ltd (Johannesburg & Botswana)
**Client Lead:** Justin
**Date Started:** March 2026
**Repository:** GitHub (under Optimum Quality organisation)
**Live URL:** portal.boldmarkprop.co.za (Phase 0 — sign-in page complete)

### What Is This System?

A modern, full-stack property management platform purpose-built for South African and Botswana Body Corporate, HOA, and rental managing agents. It is designed to:

1. Replace and surpass WeConnectU for Bold Mark Properties' day-to-day operations
2. Function as a white-labelled multi-tenant SaaS platform that Bold Mark can resell to other property management companies
3. Initially target the Botswana market (where WeConnectU's SA-specific integrations don't apply), then expand back to South Africa

The system manages the full lifecycle of property management: financials, billing across all charge types (levies, rent, utilities, deposits, penalties, and more), debt management, compliance, maintenance, communications, document management, meetings, and owner/trustee portals.

### Three Core Architectural Principles

These principles are baked into every layer of the system — data model, billing engine, UI, and business rules. They are non-negotiable.

1. **Dual-Role Unit Model:** A single property can be both a sectional title scheme (owners pay levies to the body corporate) and a rental portfolio (tenants pay rent to landlords), with Bold Mark managing both sides simultaneously. The data model and billing engine handle this natively.

2. **Flexible Charge Type System:** Beyond levies and rent, the system supports a full spectrum of property management charges — special levies, utility recoveries, deposits, penalties, facility fees, and unlimited custom charges — through a configurable ChargeType lookup table. Managing agents can add their own charge types without developer intervention.

3. **Simplicity Through Automation:** Despite the underlying flexibility, the daily experience for the admin is simple. The system auto-determines who gets invoiced for what based on estate type, unit occupancy, and charge type configuration. The admin confirms and dispatches.

---

## 2. The Client — Bold Mark Properties

**Company:** Bold Mark Properties (Pty) Ltd
**Website:** www.boldmarkprop.co.za
**Tagline:** "Moving People Forward"
**Mission:** Remove the stress from the daily running of community schemes. Maintain and manage properties with honesty, transparency and passion.
**Registrations:** NAMA-9141 | PPRA Registered: 202603011001590
**Offices:** Johannesburg (112 Boeing Rd, Bedfordview) | Botswana (The Office, Fairgrounds, Gaborone) | London (1 Harbour Exchange Square)

### Services They Offer

| Service | Description |
|---|---|
| Sectional Title Scheme Management | Full body corporate administration — levies, compliance, meetings, maintenance |
| Residential Rental Management | Tenant sourcing, lease management, rent collection, maintenance |
| Commercial Rental Management | Commercial property tenant management and lease administration |
| Property Sales & Leasing | Full support from first-time buyers to seasoned investors |
| Financial Services | Transparent accounting, budgeting, comprehensive reporting |
| Insurance | Building insurance procurement and claims management |
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

### Pain Points Identified

1. **WeConnectU is SA-specific** — bank integrations, compliance modules, payment gateways, and CSOS features are all hardwired for South Africa. Bold Mark's Botswana expansion needs a platform that works outside SA.
2. **No brand ownership** — using WeConnectU means presenting someone else's interface to clients. BoldMark PMS gives them their own branded platform.
3. **Repetitive daily tasks** — the team has flagged repetitive workflows. The system must reduce manual work through automation and smart defaults.
4. **Debt and finance tracking** — explicitly called out as the top priority. Deep financial tracking, levy arrears management, and debt control are critical.
5. **Complexity in daily workflow** — trustees, directors, and the management team find WeConnectU overwhelming. We need to simplify without sacrificing depth.
6. **No resale capability** — Bold Mark wants to eventually resell this platform to other managing agents. WeConnectU doesn't offer that.

### What the Client Wants

- A system that does **everything WeConnectU does, but better**
- **Modern, professional, premium design** consistent with their brand identity
- **Full flexibility** in billing — levies, rent, utilities, deposits, penalties, and custom charges all in one system
- **Dual-role support** — handle body corporate management and rental management simultaneously on the same property
- **Depth in finance and debt management** — the top priority feature area
- **A platform they can resell** — full SaaS multi-tenancy to onboard other managing agents
- **Phased delivery** — build progressively, each phase immediately usable

---

## 4. Competitive Reference — WeConnectU

> WeConnectU (app.weconnectu.co.za) is the system Bold Mark currently uses.
> Bold Mark's existing owner login points to app.weconnectu.co.za/signin.
> We must understand and match all its capabilities, then go beyond them.

WeConnectU is the leading South African property management platform (1,200,000+ assets under management, 1,800+ clients, 20,000+ property professionals). It offers three integrated products:

### 4.1 Community Management System (CMS) — Primary Reference

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
- Job card creation and tracking (via RedRabbit integration)
- Task management: create, assign, track across communities
- Global task dashboard across all communities
- Detailed inspections, maintenance ticket organization, live dashboard

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

### 4.2 WeConnectU Features We Must Match or Beat

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
| Netcash debit order integration | Phase 3 — payment gateway integration |

### 4.3 Where We Go Beyond WeConnectU

- **Superior UX/UI** — modern, clean, professional design vs. WeConnectU's dated interface
- **Bold Mark branding** — first-party branded experience for their clients
- **Multi-tenant SaaS with reseller model** — WeConnectU is not resellable by clients
- **Flexible charge type system** — configurable billing categories vs. hardcoded charge types
- **Dual-role unit model** — native support for mixed owner/tenant estates
- **AI-native workflows** — automation and smart suggestions from day one
- **Better reporting** — richer, exportable, customizable report builder
- **Botswana market support** — not locked to SA-specific integrations

### 4.4 Competitor Landscape

| Platform | Focus | Key Strength |
|---|---|---|
| WeConnectU | Body Corp, HOA, Rentals | Most complete SA platform |
| PayProp | Rental trust accounting | Best payment automation |
| PropWorx | Estate agency (rentals/sales) | Strong inspection module |
| MRI Software SA | Enterprise real estate | IFRS-grade financials |
| Lexpro | Sectional title accounting | STSMA-specific compliance depth |
| PowerProp | Sectional title + rentals | Solid traditional accounting |

---

## 5. Core Design Principles

> Every section of this document — data model, billing, cashbook, UI, business rules — flows from these three principles.

### 5.1 The Dual-Role Unit Model

**The real-world problem:** In property management, a single physical property often involves two overlapping management functions:

- **Body Corporate Management:** The property is a sectional title scheme. Owners pay monthly levies to fund shared expenses (security, maintenance, insurance, reserves). The owner is liable regardless of whether they live in the unit or rent it out.
- **Rental Portfolio Management:** Some owners rent out their units. A tenant occupies the unit and pays rent to the landlord (the owner). Bold Mark often manages this rental relationship too.

For a single unit, there can be **two simultaneous financial flows** managed by Bold Mark:
- **Levy:** Owner → Body Corporate (managed by Bold Mark)
- **Rent:** Tenant → Owner/Landlord (managed by Bold Mark)

**The design solution:**

Every unit has three layers of identity:
- **The Unit itself:** physical identity (unit number, address, size)
- **The Owner:** the registered owner, always present, liable for levies in sectional title schemes
- **The Occupant:** who actually lives there — either the owner themselves (owner-occupied) or a tenant (rented out)

This is modelled through an `occupancy_type` field on each unit:
- `owner_occupied` — The owner lives in the unit. Only levy billing applies (in sectional title estates).
- `tenant_occupied` — The owner has rented to a tenant. Both levy (to owner) and rent (to tenant) may apply. Tenant details stored separately.
- `vacant` — The unit is empty. Levies still apply to the owner, but no rent is collected.

**Automatic billing logic by configuration:**
- Sectional title estate + owner-occupied = levy invoice to owner
- Sectional title estate + tenant-occupied = levy invoice to owner AND rent invoice to tenant
- Rental-only estate + tenant-occupied = rent invoice to tenant only
- Any estate + vacant unit = levy invoice to owner (if sectional title), no rent

When the admin clicks "Run Billing," the system generates the correct invoices for the correct people, automatically. No manual selection of who gets what.

### 5.2 The Flexible Charge Type System

Property management involves far more than just levies and rent. Special levies, utility recoveries, deposits, penalties, facility fees, legal costs, and ad-hoc charges are all part of daily operations. Rather than hardcoding a `billing_type` enum that will inevitably need expanding, the system uses a configurable `ChargeType` lookup table.

**How it works:**
- The `ChargeType` table holds all billing categories available to the managing agent
- Each charge type has a name, description, and configuration flags that control its behaviour
- The system ships with **locked defaults** (Levy and Rent) that cannot be deleted because the billing engine depends on them
- It also ships with **common presets** covering the most frequent property management charges — these can be renamed, hidden, or deleted by the admin
- The admin can create **unlimited custom charge types** for anything specific to their operation
- Every financial transaction — invoices, cashbook entries, age analysis — references a `charge_type_id`, not a hardcoded string

**This means:**
- Monthly "Run Billing" auto-generates invoices for recurring charge types (levy, rent, parking, pet levy, etc.) based on unit configuration
- Admin can create ad-hoc billing runs for once-off charge types (special levy, insurance excess, moving fee) — selecting which units, the amount, preview, confirm, dispatch
- Cashbook filters and reports by any charge type
- Age analysis breaks down arrears by charge type or shows a combined view
- New charge types added at any time without code changes

### 5.3 Simplicity Through Automation

The dual-role model and flexible charge types introduce real power, but the admin's daily experience must remain simple:

- **Progressive disclosure:** tenant fields appear only when `occupancy_type = tenant_occupied`. Charge type options show only what's enabled for the estate.
- **Smart defaults:** estate type auto-configures which charge types are active. The billing engine auto-determines who gets invoiced for what.
- **One-click operations:** Run Billing, Download Age Analysis, Export Cashbook — each a single action with a confirmation step.
- **Visual clarity:** colour-coded badges for occupancy type, payment status, and charge type categories.
- **Forgiving workflow:** tenant archiving is reversible, cashbook allocation can be undone, billing runs can be previewed before dispatch.

### 5.4 Real-World Scenarios the System Must Handle

**Scenario A: Pure Sectional Title Estate (e.g., body corporate complex in Gaborone)**
1. 30 units, all owner-occupied
2. Admin runs billing → 30 levy invoices to owners. Some units also have parking rental and pet levy charges configured → additional invoices generated automatically.
3. One-off special levy approved at AGM → admin creates ad-hoc billing run for "Special Levy" charge type, selects all units, sets amount → 30 special levy invoices generated.

**Scenario B: Sectional Title with Mixed Occupancy (most common real-world case)**
1. 50 units: 30 owner-occupied, 15 tenant-occupied, 5 vacant
2. Monthly billing run → 50 levy invoices to all owners + 15 rent invoices to tenants + utility recovery invoices for units with metered charges
3. Two cashbook streams: levy/charge collections (body corporate account) and rent collections (trust/rental account)
4. Age analysis shows arrears broken down by charge type: levy, rent, water, electricity, etc.

**Scenario C: Pure Rental Portfolio (e.g., a landlord with 10 houses)**
1. 10 houses, all tenant-occupied, no body corporate
2. Monthly billing → 10 rent invoices + damage deposit invoices for new tenants + ad-hoc charges as needed
3. Single cashbook stream for rent and related collections

---

## 6. Data Model

> The data model implements the three core principles: dual-role units, flexible charge types, and simplicity through smart defaults. Every model is scoped by `tenant_id` (organisation) for multi-tenancy.

### 6.1 Organisation (Tenant)

The top-level entity representing a managing agent company. This is the multi-tenancy boundary.

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| name | String | Yes | e.g. "Bold Mark Properties" |
| logo_url | String | No | Company logo for invoices and branding |
| contact_email | String | Yes | Primary contact |
| contact_phone | String | No | Phone number |
| address | Text | No | Physical address |
| country | String | Yes | ZA, BW, etc. |
| primary_color | String | No | Hex colour for white-label branding |
| subdomain | String | No | e.g. "boldmark" for boldmark.oursystem.com |

### 6.2 Charge Type

**The central reference for all billing categories in the system.** Every invoice, cashbook entry, and report references a `charge_type_id`. This replaces any hardcoded billing_type enum.

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| organisation_id | FK | Yes | Scoped per organisation |
| code | String | Yes | Unique short code: `LEVY`, `RENT`, `SPECIAL_LEVY`, etc. |
| name | String | Yes | Display name: "Levy", "Rent", "Special Levy", etc. |
| description | String | No | Help text shown in UI |
| is_system | Boolean | Yes | `true` for Levy and Rent — cannot be deleted |
| is_active | Boolean | Yes | Allows hiding without deleting |
| applies_to | Enum | Yes | `owner` / `tenant` / `either` — determines who can be billed |
| is_recurring | Boolean | Yes | `true` = included in monthly Run Billing. `false` = ad-hoc only. |
| sort_order | Integer | No | Display ordering in dropdowns and reports |

#### 6.2.1 Default Charge Types

The system ships with these charge types on organisation creation. Locked defaults (Levy and Rent) cannot be deleted or have their code changed. Common presets can be renamed, hidden, or deleted. Admin can create unlimited custom charge types.

**Locked Defaults (cannot be deleted):**

| Charge Type | Code | Description | Applies To | Recurring |
|---|---|---|---|---|
| Levy | `LEVY` | Regular monthly body corporate levy | Owner | Yes |
| Rent | `RENT` | Regular monthly rental payment | Tenant | Yes |

**Common Presets (can be renamed, hidden, or deleted):**

| Charge Type | Code | Description | Applies To | Recurring |
|---|---|---|---|---|
| Special Levy | `SPECIAL_LEVY` | Once-off body corporate charge approved at AGM or special meeting | Owner | No |
| Water Recovery | `WATER_RECOVERY` | Metered water billed per unit | Either | Yes |
| Electricity Recovery | `ELECTRICITY_RECOVERY` | Metered electricity billed per unit | Either | Yes |
| Gas Recovery | `GAS_RECOVERY` | Metered gas billed per unit | Either | Yes |
| Sewerage Recovery | `SEWERAGE_RECOVERY` | Sewerage charges billed per unit | Either | Yes |
| Refuse Recovery | `REFUSE_RECOVERY` | Refuse/waste collection billed per unit | Either | Yes |
| Late Payment Interest | `LATE_INTEREST` | Interest charged on overdue balances | Either | No |
| Late Payment Penalty | `LATE_PENALTY` | Flat penalty fee for late payment | Either | No |
| Insurance Excess | `INSURANCE_EXCESS` | Damage-related excess billed back to a unit | Owner | No |
| Key Deposit | `KEY_DEPOSIT` | Deposit for keys or access devices | Tenant | No |
| Damage Deposit | `DAMAGE_DEPOSIT` | Security/damage deposit held against the unit | Tenant | No |
| Parking Rental | `PARKING_RENTAL` | Monthly parking bay rental | Either | Yes |
| Storage Rental | `STORAGE_RENTAL` | Monthly storage unit rental | Either | Yes |
| Moving-In Fee | `MOVING_IN` | Once-off fee charged when a tenant moves in | Tenant | No |
| Moving-Out Fee | `MOVING_OUT` | Once-off fee charged when a tenant moves out | Tenant | No |
| Access Card Fee | `ACCESS_CARD` | Once-off or replacement fee for access cards/remotes | Either | No |
| Gym Access | `GYM_ACCESS` | Recurring fee for gym or fitness facility | Either | Yes |
| Pool Access | `POOL_ACCESS` | Recurring fee for pool facility | Either | Yes |
| Garden Maintenance | `GARDEN_MAINT` | Individual garden maintenance charge for units with private gardens | Owner | Yes |
| Pet Levy | `PET_LEVY` | Recurring monthly charge for pet-owning residents | Either | Yes |
| Security Contribution | `SECURITY_CONTRIB` | Additional security charge beyond the standard levy | Owner | Yes |
| Legal Recovery | `LEGAL_RECOVERY` | Recovery of legal costs incurred in collections | Either | No |

#### 6.2.2 Charge Type Configuration per Estate

Not all charge types apply to every estate. A junction table (`EstateChargeType`) controls which charge types are active for each estate. When an admin creates an estate and selects the type, the system auto-enables the relevant charge types:

- **Sectional title:** Levy, Special Levy, Water/Electricity/Gas/Sewerage/Refuse Recovery, Late Payment Interest, Late Payment Penalty, Insurance Excess, Parking Rental, Storage Rental, Access Card Fee, Gym Access, Pool Access, Garden Maintenance, Pet Levy, Security Contribution, Legal Recovery
- **Residential rental:** Rent, Key Deposit, Damage Deposit, Moving-In Fee, Moving-Out Fee, Late Payment Interest, Late Payment Penalty, Parking Rental, Pet Levy, Legal Recovery
- **Commercial rental:** Rent, Key Deposit, Damage Deposit, Late Payment Interest, Late Payment Penalty, Parking Rental, Storage Rental, Legal Recovery
- **Mixed:** All charge types enabled

The admin can override these defaults — enabling or disabling any charge type per estate at any time.

#### 6.2.3 Charge Type Configuration per Unit (UnitChargeConfig)

For recurring charge types (parking, pet levy, gym, etc.), the admin configures which units are subscribed and at what amount:

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| unit_id | FK | Yes | Which unit |
| charge_type_id | FK | Yes | Which charge type |
| amount | Decimal | Yes | Monthly amount for this charge on this unit |
| is_active | Boolean | Yes | Whether currently billed |

When "Run Billing" executes, it checks this table to generate invoices for per-unit recurring charges in addition to the standard levy/rent.

### 6.3 Estate / Property

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| organisation_id | FK | Yes | Parent company (multi-tenancy scope) |
| name | String | Yes | Estate / complex name |
| address | Text | No | Physical location |
| type | Enum | Yes | `sectional_title` / `residential_rental` / `commercial_rental` / `mixed` |
| default_levy_amount | Decimal | No | Default monthly levy (sectional title / mixed) |
| default_rent_amount | Decimal | No | Default monthly rent (typically overridden per unit) |
| billing_day | Integer | No | Day of month billing runs |

When the admin selects the estate type, the system auto-enables the relevant charge types via the EstateChargeType junction table.

### 6.4 Unit

**The central entity.** Always has an owner. Optionally has a tenant. The `occupancy_type` drives all billing logic.

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| estate_id | FK | Yes | Parent estate |
| unit_number | String | Yes | Unit / plot / house number |
| address | Text | No | Physical address of unit |
| occupancy_type | Enum | Yes | `owner_occupied` / `tenant_occupied` / `vacant` |
| status | Enum | Yes | `active` / `suspended` / `vacated` |
| levy_override | Decimal | No | Unit-specific levy amount (overrides estate default) |
| rent_amount | Decimal | No | Monthly rent (required if tenant_occupied) |

### 6.5 Owner

Every unit has exactly one owner record. Always present. In an owner-occupied unit, the owner is also the occupant. In a tenant-occupied unit, the owner is the landlord.

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| unit_id | FK | Yes | The unit this owner owns |
| full_name | String | Yes | Owner full name |
| id_number | String | No | National ID / passport |
| email | String | Yes | For invoice delivery |
| phone | String | No | Phone number |
| address | Text | No | Owner address (may differ from unit) |

### 6.6 Tenant

Present only when `occupancy_type = tenant_occupied`. Soft-deleted (archived) when a unit transitions away from tenant-occupied, preserving history. **Tenant records are never hard-deleted.**

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| unit_id | FK | Yes | The unit this tenant occupies |
| full_name | String | Yes | Tenant full name |
| id_number | String | No | National ID / passport |
| email | String | Yes | For invoice delivery |
| phone | String | No | Phone number |
| lease_start | Date | No | Lease start date |
| lease_end | Date | No | Lease end date |
| is_active | Boolean | Yes | Current active tenant (soft-delete flag) |

### 6.7 Invoice

Invoices reference a `charge_type_id` and use a polymorphic `billed_to` to address either an owner or a tenant.

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| unit_id | FK | Yes | Which unit |
| charge_type_id | FK | Yes | Which charge type |
| billed_to_type | Enum | Yes | `owner` / `tenant` |
| billed_to_id | UUID | Yes | FK to Owner or Tenant record |
| invoice_number | String | Yes | Auto-generated sequence |
| billing_period | Date | Yes | Month/year (e.g. April 2026) |
| amount | Decimal | Yes | Billed amount |
| status | Enum | Yes | `unpaid` / `paid` / `partially_paid` / `overdue` — payment status only. Never conflated with email dispatch. |
| sent_at | Timestamp | No | When invoice email was dispatched. `null` = not yet sent. Populated = dispatched. This is the dispatch track, completely separate from `status`. |
| due_date | Date | Yes | Payment due date |

### 6.8 Cashbook Entry

The cashbook is a ledger of actual bank transactions, independent of invoices. Entries can exist without a matching invoice (advance payments, overpayments, deposits). The `charge_type_id` is auto-set from the invoice on allocation, or manually tagged for advance payments.

| Field | Type | Required | Notes |
|---|---|---|---|
| id | UUID | Yes | Primary key |
| estate_id | FK | Yes | Which estate |
| date | Date | Yes | Transaction date |
| description | String | Yes | Bank statement description |
| amount | Decimal | Yes | Transaction amount |
| type | Enum | Yes | `credit` / `debit` |
| charge_type_id | FK | No | Auto-set from invoice on allocation, or manual for advance payments |
| unit_id | FK | No | Allocated unit |
| invoice_id | FK | No | Allocated invoice |
| parent_entry_id | FK | No | If this entry was created by splitting a larger payment, references the original entry |
| notes | String | No | Admin notes (e.g., "Advance payment — May and June levies") |

**Allocation status is derived, not stored:**

- `invoice_id` is set → **Allocated** (green badge)
- `invoice_id` is null → **Unallocated** (amber badge)

No `allocated` boolean field exists. The presence of `invoice_id` is the single source of truth.

#### 6.8.1 Partial Allocation & Payment Splitting

When a cashbook entry amount exceeds the invoice amount, the system splits the entry:

1. Admin allocates a R3,000 payment to an invoice of R2,850
2. The system creates **two entries** from the original:
   - Entry A: R2,850, linked to the invoice (`invoice_id` set) → **Allocated**
   - Entry B: R150, no invoice link (`invoice_id` null) → **Unallocated**, with `parent_entry_id` referencing the original entry and `unit_id` preserved so the credit stays associated with the correct person
3. The original entry is soft-deleted or archived (replaced by the two child entries)

This ensures every entry is either fully allocated or fully unallocated — no partial states.

**Advance payment workflow:**

When someone pays months ahead (e.g., R8,550 = 3 months of R2,850 levy):
1. Admin allocates R2,850 to the current month's invoice → split creates allocated entry + R5,700 unallocated remainder
2. The R5,700 sits in the unallocated queue with a note like "Advance payment — May and June levies"
3. When next month's billing runs, admin sees the unallocated R5,700 credit for that unit and allocates R2,850 to the new invoice → another split, R2,850 remaining
4. Process repeats until the advance is fully consumed

**Overpayment workflow:**

When someone pays more than owed by mistake (e.g., R3,000 on a R2,850 invoice):
1. Admin allocates to the invoice → split creates R2,850 allocated + R150 unallocated
2. The R150 sits as an unallocated credit on the unit
3. When next month's invoice is generated, admin allocates the R150 → the owner only owes R2,700 for that month

#### 6.8.2 Credit Balances

Unallocated cashbook entries with a `unit_id` represent **credit balances** (money received but not yet matched to an invoice). These credits must surface in the UI:

- **Unit Detail page:** payment history section shows unallocated credits as pending credits, and the unit's financial summary shows the net position (outstanding invoices minus unallocated credits)
- **Age Analysis:** a person's total outstanding should be shown net of any unallocated credits on their units, so the arrears figure reflects what they actually owe after accounting for advance payments
- **Statements:** when generating statements, unallocated credits for the person appear as "Credit on account" line items, reducing the total due
- **Allocation queue:** the cashbook unallocated view shows these credits prominently so the admin can quickly allocate them when new invoices are created

> **Key rule: Every payment that arrives must eventually be allocated to an invoice.** If no invoice exists yet (advance payment, overpayment), the money sits as an unallocated credit until a future invoice absorbs it. If money arrives and there's truly no invoice for it (e.g., a once-off deposit), the admin should create an ad-hoc invoice first, then allocate. The goal is zero unallocated entries — every rand accounted for.

### 6.9 Age Analysis (Computed View)

Not a stored table — computed from invoices and cashbook entries. Produces arrears broken down by charge type, grouped by person (owner or tenant). Supports filtering by charge type, estate, and date range. Ageing buckets: Current / 30 / 60 / 90 / 120+ days.

---

## 7. Phase 1 — MVP Workflow

> Phase 1 delivers the core operational loop for managing estates in Botswana, with full support for the dual-role unit model and flexible charge types from day one.
> **Budget:** BWP 15,000 (agreed 2 April 2026). **Target:** End of April 2026.

### Step 1: Estate & Unit Setup

1. Admin creates an estate: name, address, type (`sectional_title`, `residential_rental`, `commercial_rental`, `mixed`), default levy amount, billing day
2. Estate type auto-configures which charge types are active for that estate
3. Admin uploads unit data via Excel (.xlsx) bulk import including: unit number, owner details (name, ID, phone, email, address), occupancy type, and if tenant-occupied: tenant details (name, email, phone, rent amount, lease dates)
4. Column-mapping preview before import. Validates required fields, flags duplicates and invalid emails. Import summary shows records imported, skipped, errors with row-level detail.
5. After upload, estate view shows all units. Clicking a unit shows: owner details (always), tenant details (if tenant-occupied), billing configuration (active charge types and amounts for this unit), and payment history across all charge types.
6. Admin can add/edit units individually. The form adapts dynamically based on occupancy type and estate charge type configuration.
7. Admin can configure per-unit recurring charges beyond levy/rent: e.g., enable Parking Rental at P150/month for Unit 12, enable Pet Levy at P75/month for Unit 5.

### Step 2: Billing & Invoice Generation

1. Admin selects an estate and clicks "Run Billing" for a given billing period (month/year)
2. The system auto-determines which invoices to generate based on estate type, unit occupancy, and per-unit charge type configuration
3. Billing preview screen: table showing each unit, each invoice to be generated (charge type, recipient, amount). Admin reviews and confirms before dispatch.
4. Invoices generated as branded PDFs. Each invoice clearly states the charge type (e.g., "Levy Invoice", "Rent Invoice", "Parking Rental Invoice")
5. Invoices emailed in bulk to the respective recipients (owner for owner-billed charges, tenant for tenant-billed charges)
6. For ad-hoc/once-off charges (special levy, insurance excess, moving fee), admin uses "Create Ad-Hoc Billing": select charge type, select units (all or specific), set amount (uniform or per-unit), preview, confirm, dispatch.

### Step 3: Payment Recording (Manual Cashbook)

1. Owners and tenants pay via bank EFT (no online payment gateway in Phase 1)
2. Admin enters bank statement transactions into the manual cashbook: date, description, amount, credit/debit
3. When allocating to a unit, the system shows all outstanding invoices grouped by charge type. Admin selects which invoice to allocate against. The charge type is auto-tagged from the invoice.
4. If the payment amount exceeds the invoice amount, the system splits the entry: the invoice amount is allocated, and the remainder becomes a new unallocated entry (credit on account) linked to the same unit. See Section 6.8.1.
5. For advance payments (no matching invoice yet), the entry sits as unallocated with the unit tagged. When future billing runs, the admin allocates the credit to new invoices.
6. Unallocated entries remain in a holding queue with clear "unallocated" status until matched. The goal is zero unallocated entries.

### Step 4: Age Analysis Report

- Customer Age Analysis generated per estate
- Report supports filtering by charge type: show all, or drill into levy only, rent only, utilities only, etc.
- Default view groups by person (owners and tenants separately) and shows outstanding balance with standard ageing buckets (Current, 30, 60, 90, 120+ days)
- Detailed view breaks down each person's outstanding by individual charge type
- Combined summary shows total exposure per unit across all charge types
- Downloadable via standard `AppExportModal` (CSV, Excel, PDF — with record count selection)

### Step 5: Payment Notices (Deferred)

Payment/arrears notices handled by external legal AI system. Not in scope for Phase 1. Age analysis export provides the data.

---

## 8. UI Screens — Phase 1

> The UI must be clean, intuitive, and optimised for daily operations. The dual-role and charge type complexity must be invisible to the admin — the system handles it behind the scenes.
>
> **Design reference:** A Lovable prototype has been built at `id-preview--18d1d863-5dd5-4d22-aa53-93670ee273e0.lovable.app`. The specifications below are informed by that prototype and represent the authoritative UI spec for the production build.

### 8.0 Global UI Patterns

These patterns apply across ALL screens in the application:

- **Shared modal component:** All modals (Add Estate, Add Unit, Invite User, etc.) use a single reusable `AppModal` component. Never rebuild modals inline.
- **Skeleton loading:** When any screen or data section is loading, show placeholder skeleton cards/rows with a shimmer animation effect. Estate list shows 6 skeleton cards. Tables show 5 skeleton rows.
- **Pagination — card grids (e.g., Estates page):** Card-based views use continuous scroll (infinite scroll). Initial load shows first 15 cards. Scrolling down seamlessly loads the next 15. No "Load More" button. Skeleton cards (6) shown during loading with shimmer effect.
- **Pagination — data tables (e.g., Units, Invoices, Cashbook, Age Analysis, Users):** Tables use button-based pagination at the bottom of the table. Shows page numbers with Previous/Next buttons. Format: `Previous | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | ... | 102 | 103 | Next`. Current page highlighted (purple/primary fill). 15 rows per page by default. Embedded tables within detail pages (e.g., unit invoices, tenant payments) use 10 rows per page.
- **Search:** All list screens include a search bar at the top. Search filters results in real-time (client-side for loaded data, server-side for full dataset).
- **Currency formatting:** Always use space-separated thousands with currency prefix: `R 2 850`, `P 1 500`, `R 1 339 700`. Never use commas for thousands.
- **Hover effects on cards:** Estate cards and similar clickable cards show an amber/gold border and subtle shadow on hover.

#### Reusable Table Toolbar (`AppTableToolbar`)

This is a **platform-wide reusable component** used on every data table in the system (units, invoices, cashbook entries, age analysis, users, etc.). It must be built once as `AppTableToolbar` and reused everywhere. It provides:

**Toolbar row (always visible above the table):**
- **Search input** (left) — text search, placeholder is context-dependent (e.g., "Search units...", "Search invoices...")
- **Date Range button** (right) — opens Date Range modal
- **Filter button** (right) — opens Filter modal. Shows badge with active filter count (e.g., "Filter 2")
- **Sort button** (right) — opens Sort modal. Shows badge with active sort count

**Date Range modal:**
- Preset options as large clickable buttons in a 2-column grid: Today | This Week | This Month | This Year | Custom | All Time
- "Custom" opens a date picker for start/end dates
- "All Time" is the default (selected/highlighted state)
- Selection applies immediately and closes modal

**Filter modal ("Add Filters"):**
- Lists all filterable fields for the current context as dropdown selectors
- Each dropdown defaults to "Any [field]" (no filter)
- Multiple filters can be combined (e.g., occupancy = Tenant AND balance > 0)
- Dropdown options are specific to the data context — the table passes its available filter fields to the component
- For units table: Occupancy (Owner / Tenant / Vacant), Balance status, Charge type
- For invoices table: Charge type, Status (Paid / Overdue / Sent / Draft), Recipient type (Owner / Tenant)
- For cashbook: Allocation status (Allocated / Unallocated), Charge type, Credit/Debit

**Sort modal ("Sort By"):**
- Lists sort options in logical groups
- Within a group, options are mutually exclusive (radio-style) — e.g., "Newest first" and "Oldest first" are one group
- Across groups, options can be combined (multi-select with checkmarks) — e.g., "Oldest first" + "Highest balance"
- Selected options show a checkmark and highlighted text (amber/purple)
- Backend prioritises sort order based on selection sequence
- Buttons at bottom: "Clear Sort" (outline) | "Done" (primary/filled)
- For units table sort groups: Date (Newest first / Oldest first), Balance (Highest balance / Lowest balance), Unit (A-Z / Z-A)
- For invoices: Date, Amount, Status
- For cashbook: Date, Amount, Allocation status

**Active filter/sort pills (below toolbar, above table):**
- Each applied date range, filter, or sort shows as a removable pill/chip
- Pill format: "This Week ×", "Status → Overdue ×", "Oldest first ×"
- Date range pills use green tint, filter pills use purple tint, sort pills use amber/gold tint
- "Clear all" link at the end removes all applied filters/sorts
- Removing a pill immediately updates the table data

**Saved Views (tabs above the toolbar):**
- Tab bar showing: "All" (default, always present) + saved custom views + "+ Add View" button (dashed border)
- Default views ship with the platform for common use cases (context-dependent)
- Users can create custom views that save a specific combination of date range + filters + sorting
- **"+ Add View" button** → opens Create View modal
- Each saved view tab shows the view name. On hover, an edit icon (pencil) and close icon (×) appear
- Clicking a saved view tab applies its saved configuration instantly
- Saved views persist per user per context (e.g., a user's saved views on the units table are separate from their saved views on invoices)

**Create View modal:**
- View Name (text input, placeholder: "e.g. Overdue Tenants, High Balance...")
- Date Range (collapsible accordion section — same preset options as Date Range modal)
- Filters (collapsible accordion section — same filter dropdowns as Filter modal)
- Sorting (collapsible accordion section — same sort options as Sort modal)
- Buttons: "Cancel" (outline) | "Save View" (primary/filled)

### 8.1 Sidebar Navigation

The sidebar is the primary navigation and is consistent across all screens. Its background colour and accent colour are controlled by the organisation's branding settings (see Section 8.16.2).

**Layout:**
- Company name and slogan at top — pulled from Settings → Company tab fields: "Company Name" and "Company Slogan" (e.g., "Bold Mark Properties" / "Property Management"). Updating these fields in Settings updates the sidebar for all users.
- "MENU" section label
- Navigation items with icons:
  - Home (house icon) — `/dashboard`
  - Estates (grid icon) — `/estates`
  - Billing (document icon) — `/billing`
  - Cashbook (wallet icon) — `/cashbook`
  - Age Analysis (trend line icon) — `/age-analysis`
  - Users (people icon) — `/users`
- Bottom section:
  - Settings (gear icon) — `/settings`
  - Collapse (toggle icon) — collapses sidebar to icon-only mode

**Active state:** Active menu item has amber/gold text colour with a semi-transparent highlight background that extends to the right edge.

**Inactive state:** White/light text, no background.

### 8.2 Top Bar (Global Header)

Present on all screens:
- **Global search bar** (left): "Search estates, units, people..." — searches across all entities
- **Notification bell** (right): with unread count badge (amber circle with white number)
- **User profile** (far right): dark navy avatar circle with person icon, full name (bold), role label below (muted, e.g., "Company Admin"), chevron icon (^) that rotates on open. Clicking opens a dropdown with two options:
  - **Account Settings** (gear icon) — navigates to `/settings` (Account tab)
  - **Log Out** (arrow-right icon, **red text**) — ends session, redirects to sign-in page

### 8.3 Authentication
- Sign-in page (already built at portal.boldmarkprop.co.za)
- Two-column layout: navy brand panel left, white form right
- Password reset flow via Resend
- Session management

### 8.4 Dashboard (Home)

**Route:** `/dashboard`

**Summary cards (top row — 4 cards):**

| Card | Value | Subtitle/Indicator |
|---|---|---|
| Total Estates | Count (e.g., "7") | Unit count subtitle (e.g., "241 units") |
| Total Outstanding | Amount in red (e.g., "R 32 050") | % change from last month with trend arrow (↗ up = bad, in red) |
| Collected This Month | Amount in black (e.g., "R 28 750") | Trend indicator (e.g., "↘ On track" in green) |
| Occupancy Rate | Percentage (e.g., "88%") | Vacant unit count (e.g., "12 vacant units") |

Each card has a small icon in the top-right corner.

**Quick action buttons (below cards):**
- "Run Billing" — amber/accent button with document icon → navigates to `/billing`
- "Upload Cashbook" — navy/primary button with upload icon → navigates to `/cashbook`
- "View Age Analysis" — outline/ghost button with trend icon → navigates to `/age-analysis`

**Recent Invoices panel (left, ~60% width):**
- Header: "Recent Invoices" with "View All →" link → navigates to `/billing`
- **Status filter tabs:** All (default, filled navy) | Paid | Overdue | Sent | Draft — pill-style toggle buttons
- Invoice list rows, each showing:
  - Invoice number (e.g., "INV-2026-0001") in bold
  - Person name + charge type (e.g., "Sarah van der Merwe · Levy") in muted text below
  - Amount right-aligned (e.g., "R 2 850")
  - Status badge right-aligned: Paid (green), Overdue (red), Sent (blue), Draft (gray)
- Clicking any invoice row → navigates to that invoice's detail page
- Shows most recent invoices, scrollable

**Estates Overview panel (right, ~40% width):**
- Header: "Estates Overview" with "View All (7) →" link → navigates to `/estates`
- List of estate cards, each showing:
  - Estate name (bold) with unit count right-aligned (e.g., "47 units")
  - Occupancy breakdown below: "X owners · Y tenants · Z vacant" in muted text
  - Note: rental-only estates show only "tenants · vacant" (no owners count) — consistent with dual-role model
- Clicking any estate → navigates to `/estates/{id}`
- Scrollable list showing all estates

### 8.5 Estates

**Route:** `/estates`

**Summary cards (top row — 4 cards):**

| Card | Value | Label |
|---|---|---|
| Estates | Total count (e.g., "7") | "Estates" |
| Units | Total across all estates (e.g., "241") | "Units" |
| Occupied | Total occupied units (e.g., "212") | "Occupied" |
| Monthly Revenue | Total across all estates (e.g., "R 1 339 700") | "Monthly Revenue" |

**Search bar:** "Search estates..." — filters estate cards in real-time.

**Estate cards grid (3 columns on desktop, responsive):**

Each estate card displays:
- **Estate name** (bold) with **type badge** (top-right):
  - "Sectional Title" — navy/dark badge
  - "Mixed" — gray badge
  - "Residential" — green badge
  - "Commercial" — amber/orange badge
- **Address** with location pin icon (muted text)
- **Occupancy stats** (3-column layout): Units | Occupied | Vacant
- **Financial info** (type-dependent):
  - Sectional title: shows "Default Levy" and "Monthly Revenue"
  - Residential rental: shows "Default Rent" and "Monthly Revenue"
  - Commercial rental: shows "Default Rent" and "Monthly Revenue"
  - Mixed: shows "Default Levy", "Default Rent", and "Monthly Revenue"
- **Hover effect:** amber/gold border and subtle shadow
- **Click:** navigates to `/estates/{id}` (estate detail page)

**Pagination:** First 15 cards loaded. Continuous scroll loads next 15 (card grid pagination pattern). Skeleton cards (6) shown during loading with shimmer effect.

**"+ Add Estate" button** (top-right, amber): opens Add Estate modal.

**Add New Estate modal:**
- Uses shared `AppModal` component
- Fields:
  - Estate Name (text input, placeholder: "e.g. Crystal Mews Body Corporate")
  - Estate Type (dropdown: Sectional Title / Residential Rental / Commercial Rental / Mixed)
  - Number of Units (number input, placeholder: "e.g. 24") — indicative/planning field, not source of truth
  - Address (text input, placeholder: "Full street address")
  - Default Levy Amount (number input) — **only shown when estate type is Sectional Title or Mixed**
  - Default Rent Amount (number input) — **only shown when estate type is Residential Rental, Commercial Rental, or Mixed**
  - Assigned Manager (dropdown/search) — selects from existing users in the system (not free-text entry). Label: "Assigned Manager" or "Primary Contact" (not "Managing Agent" — Bold Mark *is* the managing agent).
- Buttons: "Cancel" (outline) | "Create Estate" (amber, primary)
- On save: creates estate, auto-configures charge types based on type, redirects to estate detail or reloads list

### 8.6 Charge Type Management (in Settings)

**Route:** `/settings` → Company tab → Charge Types section

Charge type management lives within the Settings page (Section 8.16.2), not as a standalone screen. See Section 8.16.2 for full specification including the charge types table, Add Custom Charge Type modal, and the complete list of default charge types.

### 8.7 Estate Detail Page

**Route:** `/estates/{id}`

**Header:**
- Back arrow (←) → returns to `/estates`
- Estate name (large heading) + type badge (Sectional Title / Mixed / Residential / Commercial)
- Address with location pin icon (muted text below name)
- "Bulk Import" button (outline, with upload icon) → opens Bulk Import modal
- "+ Add Unit" button (amber) → opens Add Unit modal
- Edit Estate button (pencil icon or text) → opens Edit Estate modal (same as Add Estate modal but pre-populated, uses shared `AppModal`)

**Summary cards (6 cards in a row):**

| Card | Value | Notes |
|---|---|---|
| Units | Total count | Always shown |
| Owners | Owner-occupied count | Count of owner-occupied units |
| Tenants | Tenant-occupied count | Count of tenant-occupied units |
| Vacant | Vacant count | Always shown |
| Monthly Revenue | Total expected revenue | Calculated from all active unit billing |
| Total Balance | Net balance across all units | **Red when negative** (arrears). Shows as e.g., "-R 33 300" |

**Units section:**

Header: "Units" with people icon. Uses the **`AppTableToolbar`** component (see Section 8.0) for search, date range, filters, sorting, saved views, and active filter pills.

**Quick filter tabs** (above or alongside toolbar): All | Owners | Tenants | Vacant — pill-style toggle buttons for fast occupancy filtering.

**Units table columns:**

| Column | Content | Sortable |
|---|---|---|
| UNIT | Unit number (e.g., "A01", "B02") | Yes (↑↓) |
| OWNER | Owner name (bold) + email (muted, below) | Yes (↑↓) |
| OCCUPANCY | Badge: green "Owner" / blue "Tenant" / gray "Vacant" | No (use quick filter tabs) |
| TENANT | Tenant name if tenant-occupied, "—" if none | No |
| BALANCE | Amount. R 0 in black. Negative amounts in red (e.g., "-R 2 850") | Yes (↑↓) |

- Clicking any row → navigates to `/estates/{id}/units/{unitId}` (unit detail page)
- Table uses button-based pagination (15 rows per page) with Previous/Next controls
- Skeleton rows (5) shown during loading with shimmer effect

**Charts section (below the units table, 2-column + full-width layout):**

**Occupancy Breakdown** (left, ~50% width):
- Donut/ring chart showing percentage breakdown
- Segments: Owners (navy/dark blue), Tenants (green), Vacant (amber/gold)
- Labels with percentages: "Owners 60%", "Tenants 30%", "Vacant 11%"

**Invoice Status** (right, ~50% width):
- Bar chart showing count of invoices by status
- Bars: Paid (green), Overdue (red), Partial (amber)
- Y-axis: count. X-axis: status labels.
- Title: "Invoice Status" (not "Invoice Collection")

**Top Arrears** (full width below the two charts):
- Horizontal bar chart showing the top 5 units with highest arrears
- Each bar labelled: "Unit – Owner name" (e.g., "A02 – Ndaba", "B04 – Zulu")
- Bar colour: red/danger gradient
- X-axis: amount scale (R 0 to R 14 000 etc.)
- Sorted by highest arrears descending

**Bulk Import Units modal:**
- Uses shared `AppModal`
- Title: "Bulk Import Units"
- Description text: "Upload a CSV or Excel file to import multiple units at once. The file should contain columns for unit number, owner details, occupancy type, and charge overrides."
- **Drag-and-drop upload zone:** dashed border area with upload icon, "Drop your file here or click to browse", "Supports .csv, .xlsx (max 5MB)"
- "Choose File" button as fallback
- **"Download template file →"** link — downloads a pre-formatted Excel/CSV template with all accepted columns
- Buttons: "Cancel" (outline) | "Import" (amber)
- **After file upload, the import flow is multi-step:**
  1. **Column mapping preview:** System shows the uploaded file's columns alongside the expected system columns. User can confirm or re-map columns using dropdowns. This handles cases where column names differ between the uploaded file and the system's expected format.
  2. **Data preview table:** Shows all rows as they would appear in the units table after import. Uses the same table layout as the actual units table for visual consistency. Rows with validation errors are highlighted (e.g., missing required fields, invalid emails, duplicate unit numbers).
  3. **Import summary:** Shows count of records to import, duplicates to skip (auto-excluded to prevent double entries), and errors to fix. User confirms final import.
  4. **On import:** System creates unit records, owner records, and tenant records (where applicable). Duplicates are silently skipped. Success notification shown with count imported.

**Add Unit modal:**
- Uses shared `AppModal`
- Fields: unit number, occupancy type selector (Owner / Tenant / Vacant)
- **Owner fields (always shown):** full name, ID number, email, phone, address
- **Tenant fields (shown only when occupancy = Tenant):** full name, email, phone, rent amount, lease start date, lease end date
- **Charge overrides (collapsible section):** levy override amount (if estate has levy enabled), rent amount (if tenant-occupied)
- Buttons: "Cancel" (outline) | "Add Unit" (amber)

### 8.8 Unit Detail Page

**Route:** `/estates/{id}/units/{unitId}`

**Header:**
- Back arrow (←) → returns to estate detail page
- Unit number (e.g., "Unit A01") + occupancy badge (green "Owner" / blue "Tenant" / gray "Vacant")
- Estate name (muted subtitle, e.g., "Crystal Mews Body Corporate")
- **View tabs** (only shown when unit has a tenant): Combined | Owner | Tenant
  - Combined: shows all data for both owner and tenant on one page (default)
  - Owner: filters the entire page to show only owner-related data (owner details, owner invoices, owner payments, owner emails/logs)
  - Tenant: filters the entire page to show only tenant-related data (tenant details, tenant invoices, tenant payments, tenant history, tenant emails/logs)
- "Edit Unit" button (top-right) → opens Edit Unit modal

**Left column (contact cards + charge config):**

**Owner card (always present):**
- "Owner" heading
- Full name (bold)
- Email with envelope icon
- Phone with phone icon
- ID Number label + value
- Monthly Levy label + amount (e.g., "R 2 850") — label adapts to estate type
- "Send Message" button (full width, outline with send icon) → opens Send Message modal pre-populated with owner as recipient. **Only owner-applicable templates are shown.**

**Tenant card (shown only when occupancy_type = tenant_occupied):**
- "Tenant" heading
- Full name (bold)
- Email with envelope icon
- Phone with phone icon
- Lease dates with calendar icon (e.g., "01 Mar 2025 → 28 Feb 2026")
- Monthly Rent label + amount (e.g., "R 9 500")
- Lease Document section: filename displayed (e.g., "Lease_Agreement_2025.pdf") with preview icon (eye — opens PDF/image preview in modal) and download icon (arrow down)
- "Send Message" button → opens Send Message modal pre-populated with tenant as recipient. **Only tenant-applicable templates are shown.**

**Charge Configuration card:**
- "Charge Configuration" heading
- List of all charge types relevant to this unit and estate:
  - Configured charges: charge type name + amount right-aligned (e.g., "Levy R 2 850", "Rent R 9 500")
  - Available but unconfigured charges: charge type name + "Not configured" in muted text (e.g., "Parking Rental Not configured")
- Charge types shown depend on estate configuration (EstateChargeType)

**Right column (financial data + history):**

**Account Balance card:**
- "Account Balance" label (muted)
- Large amount display: "R 0" in black when clear, negative amounts in red (e.g., "-R 2 850")
- "In Arrears" badge (red, top-right of card) when balance is negative

**Invoices section:**
- "Invoices" heading with **"+ Create Invoice" button** (small, outline, right-aligned) → opens Create Invoice modal
- Table columns: INVOICE | TYPE | PERIOD | BILLED TO | AMOUNT | STATUS
- Status badges: Paid (green), Overdue (red), Sent (blue), Draft (gray)
- **Paginated at 10 per page** (not 15 — smaller page size for embedded tables)
- **Status filter tabs:** All | Paid | Overdue | Sent | Draft — same pill-toggle pattern as dashboard Recent Invoices
- Clicking any invoice row → navigates to invoice detail page (`/billing/invoices/{invoiceId}`)

**Payments Received section:**
- "Payments Received" heading with clipboard icon
- Table columns: DATE | DESCRIPTION | AMOUNT | INVOICE | STATUS
- Amount shown in green with + prefix for credits (e.g., "+R 2 850", "+R 9 500")
- Invoice column: linked invoice number (e.g., "INV-2026-0001")
- Status badge: "Allocated" (green outline)
- Shows all cashbook entries allocated to this unit (levy, rent, or any charge type)
- Clicking any entry → navigates to cashbook entry detail page

**Tenant History section:**
- "Tenant History" heading with clock icon
- **"Move In" button** (amber, right-aligned) — shown when unit has NO current tenant. Opens Move In New Tenant modal.
- **"Move Out" button** (outline, right-aligned) — shown when unit HAS a current tenant. Triggers move-out flow (archives tenant, updates occupancy).
- Timeline list of all tenants (current and historical):
  - **Current tenant:** highlighted card (amber/gold left border or background tint) with "Current" badge, name, lease dates, rent amount
  - **Previous tenants:** standard cards with: name, lease date range, rent/month, status note ("Left in good standing" or "Outstanding: R 2 400")
- Clicking any tenant in the history → navigates to tenant detail page (`/estates/{id}/units/{unitId}/tenants/{tenantId}`)

**Change Log / Emails section (tabbed):**
- **Tab toggle:** Change Log | Emails Sent

**Change Log tab:**
- Vertical timeline of all changes related to this unit, ordered newest first
- Each entry shows:
  - Event title (bold, e.g., "Updated owner details", "Moved out tenant", "Unit created")
  - Category badge: Owner (navy), Tenant (blue), Charges (amber), Unit (gray)
  - Changed by: person name (right-aligned)
  - Timestamp: ISO datetime (right-aligned, below name)
  - Detail bullets: specific field changes shown as "Field: old value → new value" (e.g., "Email: sipho@email.com → sipho.m@email.com")
- Event types tracked: Unit created, Updated unit details, Updated owner details, Updated charge configuration, Moved in tenant, Moved out tenant, Updated tenant details
- Clicking any entry → opens **Change Details modal** showing: event title + category badge, full list of changes, "Changed by" name, "Date & Time" timestamp

**Emails Sent tab:**
- List of all emails sent to owner or tenant for this unit
- Includes both **system-generated emails** (invoice dispatches, payment reminders, billing run notifications) and **manual emails** (sent via Send Message)
- Each entry shows: subject line, date sent, recipient (owner/tenant name), delivery status (sent / delivered / opened)
- Clicking any email → opens modal showing the full email content as it was sent to the recipient
- **Email delivery tracking (sent → delivered → opened) is critical for communication audit trail.** Uses Resend webhooks.

**Edit Unit modal:**
- Uses shared `AppModal`
- Title: "Edit Unit [unit number]" (e.g., "Edit Unit A01")
- Fields:
  - Unit Number (text input, pre-populated)
  - Occupancy Type (dropdown) — **options are estate-type-dependent:**
    - `sectional_title` estates: **Owner / Vacant only** — "Tenant" option never appears
    - All other estate types (`residential_rental`, `commercial_rental`, `mixed`): Owner / Tenant / Vacant
  - Levy Override (number input, placeholder: "Use default levy") — **only shown for estates of type `sectional_title` or `mixed`**
  - Owner Details section (always shown): Full Name, Email, Phone, ID Number — all pre-populated
  - Tenant Details (toggle + fields) — **completely hidden for `sectional_title` estates.** For all other estate types: toggle switch with label "Toggle on if this unit also has a tenant" + helper text. When toggled ON, reveals: Full Name, Email, Phone, Rent Amount, Lease Start (date picker), Lease End (date picker), Lease Document (drag-and-drop PDF upload zone)
- **Contextual hint** (bottom of modal, muted text): If fields are hidden due to estate type, show explanation: e.g., "This estate is a Residential Rental — levy override is not applicable unless the estate type is changed to Sectional Title or Mixed."
- Buttons: "Cancel" (outline) | "Save Changes" (amber)
- On save: updates records, logs all changes in the Change Log with field-level diff
- **Backend enforcement:** `CreateUnitRequest` and `UpdateUnitRequest` reject `occupancy_type = tenant_occupied` and any `tenant` payload for units in a `sectional_title` estate. This is a hard constraint enforced at both UI and API layers (see Business Rule 18).

**Move In New Tenant modal:**
- Uses shared `AppModal`
- Title: "Move In New Tenant"
- Fields: Full Name, Email, Phone, Monthly Rent (number input, placeholder: "e.g. 9500"), Lease Start (date picker), Lease End (date picker)
- Buttons: "Cancel" (outline) | "Confirm Move In" (amber)
- On save: creates tenant record, sets `is_active = true`, updates unit `occupancy_type` to `tenant_occupied`, adds entry to Tenant History with "Current" badge, logs "Moved in tenant" in Change Log

**Send Message modal:**
- Uses shared `AppModal`
- Title: "Send Message" with envelope icon
- **Recipient display** (top, non-editable): avatar (initials circle), full name, email, role badge ("Owner" or "Tenant")
- **Template dropdown:** "Select a template..." — only shows templates applicable to the recipient type:
  - Owner templates: Payment Reminder, Levy Increase Notice, Welcome Letter, Maintenance Notice, Monthly Statement
  - Tenant templates: Payment Reminder, Welcome Letter, Maintenance Notice, Lease Renewal Notice, Monthly Statement
  - **Templates are never cross-contaminated** — owner-only templates never appear for tenant recipients and vice versa
- Selecting a template **auto-populates** Subject and Message body with merge fields resolved (recipient name, unit number, estate name, balance, amount, dates, company name)
- Subject (text input, editable after template auto-fill)
- Message (textarea, editable after template auto-fill, multi-line)
- Buttons: "Cancel" (outline) | "Send Email" (amber with send icon)
- On send: dispatches email via Resend, logs in Emails Sent tab, tracks delivery status via Resend webhooks

**Create Invoice modal (from unit detail page):**
- Uses shared `AppModal`
- Title: "Create Invoice"
- **Unit info display** (non-editable, top): Unit number, estate name, owner name, tenant name (if applicable)
- **Bill To** (dropdown): "Owner" or "Tenant" — Tenant option only available if unit is tenant-occupied. For estates that are sectional title or mixed, both options appear. For rental-only estates, only "Tenant" appears.
- **Charge Type** (dropdown): Shows charge types from the estate's active charge type list, filtered by the selected "Bill To" — e.g., if billing the owner, only charge types with `applies_to = owner` or `either` appear. If billing the tenant, only `tenant` or `either` types appear.
- **Billing Period** (month/year selector): defaults to current month
- **Amount** (number input): auto-fills from charge type default if one exists (e.g., levy amount), editable
- **Description** (optional text input): additional notes for the invoice
- Buttons: "Cancel" (outline) | "Preview" (outline) | "Create & Send" (amber)
- "Preview" shows a read-only summary of the invoice before sending
- On create: generates invoice record, generates branded PDF, sends email to the recipient via Resend
- **Important:** Each invoice covers ONE charge type only. To bill multiple charges, create multiple invoices. The system generates a consolidated statement PDF that groups all invoices for a person into one document (see Section 8.10 for the Invoice vs Statement distinction).

> **Example — Creating an invoice from the unit detail page:**
> The admin is on Unit A02 (Crystal Mews, sectional title estate). The owner is Michael Ndaba, the tenant is Lisa Mokoena. The admin clicks "+ Create Invoice" and selects "Bill To: Tenant", "Charge Type: Access Card Fee", "Amount: R 350". The invoice is created for Lisa Mokoena for R 350 and emailed to her. This is separate from her monthly rent invoice — it's a one-off charge for a replacement access card.

### 8.9 Tenant Detail Page

**Route:** `/estates/{id}/units/{unitId}/tenants/{tenantId}`
- Current tenant route example: `/estates/1/units/u2/tenants/current`
- Past tenant route example: `/estates/1/units/u2/tenants/t-prev-1`

Accessed by clicking any tenant in the Tenant History list on the Unit Detail page.

**Header:**
- Back arrow (←) → returns to unit detail page
- Tenant full name (large heading, e.g., "Lisa Mokoena" or "John van Niekerk")
- Status badge: "Current Tenant" (green) or "Past Tenant" (gray outline)
- Subtitle: "Unit [number] · [Estate name]" (e.g., "Unit A02 · Crystal Mews Body Corporate")
- "Send Message" button (top-right, outline with send icon) → opens Send Message modal pre-populated with this tenant. Only tenant-applicable templates shown.
- "Edit Tenant" button (top-right, pencil icon) → opens Edit Tenant modal (see below)

**Left column:**

**Contact Details card:**
- "Contact Details" heading
- Email with envelope icon
- Phone with phone icon

**Lease Details card:**
- "Lease Details" heading
- Lease Start: date (e.g., "01 Mar 2025")
- Lease End: date (e.g., "28 Feb 2026")
- Monthly Rent: amount (e.g., "R 9 500")
- **Reason for Leaving** (only shown for past tenants): e.g., "Left in good standing", "Evicted", "Lease not renewed", "Outstanding balance"

**Lease Document card:**
- "Lease Document" heading
- File display: document icon + filename (truncated if long, e.g., "Lease_Agreement_Lisa...") + file type and size (e.g., "PDF · 245 KB")
- Preview icon (eye) → opens PDF/image preview in modal
- Download icon (arrow down) → downloads the file

**Right column:**

**Invoices section:**
- "Invoices" heading
- Table columns: INVOICE | TYPE | PERIOD | BILLED TO | AMOUNT | STATUS
- Shows only invoices billed to this specific tenant
- Status badges: Paid (green), Overdue (red), Sent (blue), Draft (gray)
- **Status filter tabs:** All | Paid | Overdue | Sent | Draft (same pill-toggle pattern)
- Paginated at 10 per page
- Clicking any invoice → navigates to invoice detail page

**Payments Received section:**
- "Payments Received" heading with clipboard icon
- Table columns: DATE | DESCRIPTION | AMOUNT | INVOICE | STATUS
- Amount in green with + prefix (e.g., "+R 9 500")
- Shows only cashbook entries allocated to this tenant's invoices
- Clicking any entry → navigates to cashbook entry detail page

**Edit Tenant modal:**
- Uses shared `AppModal`
- Title: "Edit Tenant Details"
- Fields: Full Name, Email, Phone, Monthly Rent, Lease Start (date picker), Lease End (date picker)
- Lease Document section: shows current document (if any) with option to replace. Drag-and-drop upload zone: "Drop lease PDF here or click to browse" + "Choose File" button. Uploading a new file replaces the existing one.
- Buttons: "Cancel" (outline) | "Save Changes" (amber)
- On save: updates tenant record, logs changes in unit Change Log

### 8.10 Billing & Invoicing

**Route:** `/billing`

**Page title:** "Billing & Invoicing" / "Generate and manage invoices"

**Action buttons (top-right):**
- "+ Ad-Hoc Billing" (outline) → opens Create Ad-Hoc Billing modal
- "Run Billing" (amber, with play icon) → opens Run Monthly Billing modal

**Toolbar row:**
- Search input: "Search invoices..."
- **Status filter** (dropdown): All Status | Paid | Overdue | Partial | Sent | Draft
- **Charge Type filter** (dropdown): All Charge Types | Levy | Rent | (and all other active charge types)
- **Export button** (download icon + "Export") → opens `AppExportModal` (see Section 8.10)
- The full `AppTableToolbar` pattern (date range, filter, sort, saved views) also applies here for advanced filtering

**Invoice table columns:**

| Column | Content |
|---|---|
| INVOICE # | Invoice number (e.g., "INV-2026-0001") |
| ESTATE | Estate name (e.g., "Crystal Mews BC") |
| UNIT | Unit number (e.g., "A01", "B02") |
| TYPE | Charge type (e.g., "Levy", "Rent") |
| BILLED TO | Person name |
| PERIOD | Billing period (e.g., "April 2026") |
| AMOUNT | Amount (e.g., "R 2 850") |
| STATUS | Badge: Paid (green), Overdue (red), Partial (amber), Sent (blue), Draft (gray) |

- Clicking any invoice row → navigates to invoice detail page (`/billing/invoices/{invoiceId}`)
- Button-based pagination (15 rows per page) with Previous/Next controls
- Skeleton rows during loading

**Charts section (below table, 2-column layout):**

**Invoices by Status** (left, ~50%):
- Donut/ring chart showing percentage breakdown of invoice statuses
- Segments: Paid (green), Overdue (red), Partial (amber)
- Labels with percentages: "Paid 30%", "Overdue 60%", "Partial 10%"

**Revenue by Charge Type** (right, ~50%):
- Vertical bar chart showing total revenue amount per charge type
- Bars: one per active charge type (Levy, Rent, etc.) in navy
- Y-axis: amount scale (R 0k to R 24k etc.)
- X-axis: charge type labels

#### Invoice vs Statement — Critical Distinction

> **This is a fundamental architectural concept that the coding agent must understand.**

**Invoices** are individual billing records — one charge type, one unit, one period, one recipient. They are the atomic financial transaction. The entire billing engine, cashbook allocation, and age analysis operate on individual invoices.

**Statements** are consolidated presentation documents — they group all invoices for a single person across a billing period into one branded PDF. The statement shows multiple line items (levy + parking + pet levy), previous balance carried forward, payments received, and total outstanding.

**Why this matters:**
- When the admin clicks "Run Billing", the system generates individual invoice records (one per charge type per unit). This is what populates the invoices table and what the cashbook allocates against.
- When the owner/tenant receives their email, they get a **statement PDF** that consolidates all their invoices into one document. This is what they actually read and pay from.
- The age analysis reports on individual invoices (so you can see exactly which charge types are overdue), but the statement gives the person a complete picture.

> **Example — How billing works end to end for Unit A02:**
> Unit A02 in Crystal Mews (sectional title) is owned by Michael Ndaba (owner-occupied = no, tenant-occupied). Tenant is Lisa Mokoena. Michael has parking rental configured at R 450/month.
>
> When "Run Billing" executes for April 2026:
> - Invoice 1: Levy → Michael Ndaba (owner) → R 2,850
> - Invoice 2: Parking Rental → Michael Ndaba (owner) → R 450
> - Invoice 3: Rent → Lisa Mokoena (tenant) → R 9,500
>
> Three separate invoice records created. But Michael receives ONE statement PDF showing: "Levy: R 2,850 + Parking Rental: R 450 = Total Due: R 3,300". Lisa receives ONE statement PDF showing: "Rent: R 9,500 = Total Due: R 9,500".
>
> When Michael pays R 3,300 via EFT, the admin enters it in the cashbook and allocates R 2,850 to Invoice 1 (levy) and R 450 to Invoice 2 (parking). Both invoices move to "Paid" status. Clean, traceable, auditable.

**Generate Statement** action (future enhancement for Phase 1 if time permits):
- "Generate Statements" button on billing page → select estate, billing period → system generates consolidated PDF statements per person → bulk email via Resend
- Statements can also be generated per unit from the unit detail page

#### Billing Schedule Configuration

Billing can run automatically on a schedule or manually via the "Run Billing" button.

**Company-level defaults** (configured in Settings → Billing Schedule):
- Default billing day for levies (e.g., 1st of each month)
- Default billing day for rent (e.g., 1st of each month)
- Default billing time (e.g., 08:00)
- Auto-send toggle: when enabled, the system automatically generates and emails invoices/statements on the scheduled date without admin intervention

**Estate-level overrides** (configured on estate detail → Billing Schedule section):
- Override billing day for this estate (e.g., Crystal Mews sends levies on the 25th of the prior month because their body corporate prefers it)
- Override billing time
- Override auto-send toggle (disable auto-send for a specific estate if the admin wants to review before sending)
- If no override is set, the estate inherits the company default

> **Example — Automatic vs manual billing:**
> Bold Mark's company default is: levies sent on the 1st at 08:00, rent sent on the 1st at 08:00, auto-send enabled.
>
> Crystal Mews overrides: levies sent on the 25th of the prior month (so trustees see the invoice before month-end).
>
> On March 25th at 08:00, the system automatically generates and sends levy invoices for Crystal Mews (April period). On April 1st at 08:00, it sends levy invoices for all other estates and rent invoices for all estates. The admin doesn't need to do anything — it's all automatic.
>
> But if Sandton Heights has auto-send disabled, on April 1st the system generates the invoices as drafts but doesn't email them. The admin reviews, makes any adjustments, then manually clicks "Run Billing" to dispatch.

#### Run Monthly Billing modal

- Uses shared `AppModal`
- Title: "Run Monthly Billing"
- **Estate** (dropdown): lists all estates. Selecting an estate triggers the billing preview.
- **Billing Period** (dropdown): month/year selector, defaults to current month
- **Billing Preview** section (appears after estate + period selected):
  - Header: "Billing Preview — X invoices to generate"
  - Preview table columns: Unit | Charge Type | Recipient | Amount
  - Shows every invoice that will be created, auto-determined by the billing engine (see Section 10)
  - Only shows units/people who have NOT yet received invoices for this period (prevents duplicates)
  - The preview is scrollable if many invoices
- Buttons: "Cancel" (outline) | "Confirm & Send Invoices" (amber)
- On confirm: generates individual invoice records, generates branded PDF statements per person, emails statements via Resend, invoices appear in the billing table

> **Example — Run Billing preview for Crystal Mews (sectional title, mixed occupancy):**
> Admin selects "Crystal Mews Body Corporate" + "April 2026". The preview shows:
>
> | Unit | Charge Type | Recipient | Amount |
> |---|---|---|---|
> | A01 | Levy | Sarah van der Merwe | R 2 850 |
> | A02 | Levy | Michael Ndaba | R 2 850 |
> | A02 | Rent | Lisa Mokoena | R 9 500 |
> | A02 | Parking Rental | Michael Ndaba | R 450 |
> | A03 | Levy | Johan Pretorius | R 2 850 |
> | A04 | Levy | Thandi Dlamini | R 2 850 |
> | ... | ... | ... | ... |
>
> Notice: A02 appears three times — levy to the owner, rent to the tenant, and parking rental to the owner. This is the dual-role model in action. The admin confirms, and all invoices are generated and statements emailed.

#### Create Ad-Hoc Billing modal

For once-off, non-recurring charges (special levy, insurance excess, moving-in fee, access card fee, etc.).

- Uses shared `AppModal`
- Title: "Create Ad-Hoc Billing"
- **Estate** (dropdown): lists all estates
- **Charge Type** (dropdown): shows only **non-recurring** charge types enabled for the selected estate (e.g., Special Levy, Insurance Excess, Moving-In Fee, Access Card Fee). Recurring types (Levy, Rent, Parking) are not shown here — those are handled by Run Billing.
- **Units** (dropdown): "All Units" (default) or "Select Specific Units..."
  - Selecting "Select Specific Units..." transitions the modal into a **unit selection view**:
    - Searchable list of all units in the estate
    - Each row: checkbox + unit number + owner name + occupancy badge
    - "Select All" toggle at top
    - Search bar to filter units
    - "Confirm Selection" button → returns to the main modal
    - Summary displayed: "3 units selected" as a clickable chip. Clicking re-opens the selection view to edit.
- **Amount** (number input): the amount to charge per unit (uniform across all selected units). Defaults to 0.00.
- Buttons: "Preview" (outline) | "Generate & Send" (amber)
- "Preview" shows a table of all invoices to be generated (unit, charge type, recipient, amount) — same format as Run Billing preview. Admin reviews before confirming.
- On generate: creates individual invoice records, sends statements via Resend

> **Example — Special levy for roof repair:**
> The Crystal Mews body corporate approved a R 5,000 special levy per unit for roof repair at the AGM. The admin clicks "+ Ad-Hoc Billing", selects Crystal Mews, Charge Type: Special Levy, Units: All Units, Amount: R 5,000. Preview shows 47 invoices (one per unit, all to owners since Special Levy `applies_to = owner`). Confirm — 47 invoices generated and emailed.
>
> **Example — Moving-in fee for a specific tenant:**
> A new tenant moved into Unit B04. The admin clicks "+ Ad-Hoc Billing", selects Crystal Mews, Charge Type: Moving-In Fee, Units: Select Specific Units → checks B04 only, Amount: R 1,500. One invoice generated for the tenant of B04.

#### Export Modal (`AppExportModal`)

This is a **platform-wide reusable component** used on every data table that supports exporting (invoices, cashbook entries, age analysis, units, etc.). It must be built once as `AppExportModal` and reused everywhere. A single "Export" button (download icon + "Export") in the toolbar opens this modal.

- Uses shared `AppModal`
- Title: "Export [Context]" (e.g., "Export Invoices", "Export Age Analysis", "Export Cashbook Entries")
- **Format** (radio buttons): CSV | Excel (.xlsx) | PDF
- **Records** (dropdown): "Current page" | "25 records" | "50 records" | "100 records" | "500 records" | "1 000 records"
- Note: export respects all currently applied filters, search, date range, and sorting. If the admin is viewing "Overdue" invoices for "Crystal Mews" sorted by amount, the export contains exactly that filtered dataset.
- Buttons: "Cancel" (outline) | "Download" (amber with download icon)
- On download: generates file and triggers browser download

> **Important:** There is only ONE export button per table, not separate buttons for each format. The format selection happens inside the modal. This pattern is consistent across the entire platform.

### 8.11 Invoice Detail Page

**Route:** `/billing/invoices/{invoiceId}` (e.g., `/billing/inv1`)

Accessed by clicking any invoice anywhere in the platform (billing table, unit detail invoices, tenant detail invoices, dashboard recent invoices).

**Header:**
- Back arrow (←) → returns to previous page
- Invoice number (large heading, e.g., "INV-2026-0001")
- Status badge: Paid (green), Overdue (red), Partial (amber), Sent (blue), Draft (gray)
- Subtitle: "[Estate name] · Unit [number]" (e.g., "Crystal Mews BC · Unit A01")
- Action buttons (top-right): "Print" (outline, printer icon) | "Resend Email" (outline, envelope icon) | "Download PDF" (amber, download icon)

**Left column:**

**Invoice Details card (styled as a branded document preview):**
- Company name ("Bold Mark Properties") + subtitle ("Property Management Services") + invoice number — top row
- "Tax Invoice" label (top-right of card)
- BILL TO section: person name (bold), role ("Owner" or "Tenant"), email
- Invoice Date, Due Date, Period — right-aligned
- Line items table: DESCRIPTION | AMOUNT
  - Description format: "[Charge Type] — Unit [number]" with period below (e.g., "Levy — Unit A01 / April 2026")
  - Amount right-aligned
- "Total Due" row at bottom (bold)

> **Example — Paid invoice (INV-2026-0001):**
> Bill To: Sarah van der Merwe (Owner, sarah@email.com). Invoice Date: 01 April 2026. Due Date: 07 Apr 2026. Period: April 2026. Line item: "Levy — Unit A01, April 2026: R 2 850". Total Due: R 2 850.

> **Example — Partial invoice (INV-2026-0008):**
> Bill To: James Motsepe (Owner). Total Amount: R 3 100. Total Paid: R 1 500 (green). Outstanding: R 1 600 (red). Payment History shows one partial EFT of +R 1 500.

**Payment History section (below invoice card):**
- "Payment History" heading
- Table columns: DATE | DESCRIPTION | AMOUNT
- Amount in green with + prefix (e.g., "+R 2 850")
- If no payments: "No payments received yet" (muted text, centered)
- Clicking any payment → navigates to cashbook entry detail page

**Right column:**

**Financial summary card (top):**
- Total Amount: large black text (e.g., "R 2 850")
- Total Paid: green text (e.g., "R 2 850" if fully paid, "R 0" if unpaid, "R 1 500" if partial)
- Outstanding: amount in red if > 0, "R 0" in black if fully paid

**Context card (below financial summary):**
- Estate: estate name (with building icon)
- Unit: unit number (with hash icon)
- Type: "Owner" or "Tenant" (with person icon)
- Due: due date (with calendar icon)

**Email Tracking card (below context):**
- "Email Tracking" heading with envelope icon
- Vertical timeline of email delivery events:
  - **Sent**: checkmark icon (green), timestamp, recipient email address
  - **Delivered**: circle-check icon (green), timestamp
  - **Opened**: envelope-open icon (green), timestamp — or "Not yet opened" (muted) if not opened
- Tracked via Resend webhooks. Critical for audit trail.

> **Example — Email tracking for an overdue invoice:**
> Sent: 01 Apr 2026, 09:15 AM → michael@email.com. Delivered: 01 Apr 2026, 09:15 AM. Opened: 02 Apr 2026, 11:42 AM. This tells the admin: "Michael received the invoice, opened it, but still hasn't paid." This is valuable evidence if the matter escalates to debt collection or legal action.

### 8.12 Cashbook

**Route:** `/cashbook`

**Page title:** "Cashbook" / "[Estate name] — Payment recording & allocation"

**Action buttons (top-right):**
- "Auto-Allocate" (outline, with swap icon) → attempts to automatically match unallocated credit entries to outstanding invoices based on description matching, amount matching, and unit reference patterns. Shows a preview of proposed allocations before confirming.
- "+ Add Entry" (amber) → opens Add Entry modal

**Summary cards (4 cards):**

| Card | Value | Colour | Notes |
|---|---|---|---|
| Total Credits | Amount (e.g., "R 30 600") | Green | Total of all credit entries (money received). Down-arrow icon. |
| Total Debits | Amount (e.g., "R 16 700") | Red | Total of all debit entries (money paid out). Up-arrow icon. |
| Net Balance | Amount (e.g., "R 13 900") | Black | Credits minus debits. |
| Unallocated | Count + amount (e.g., "3 entries / R 19 550 total") | Amber | Number and value of entries not yet matched to invoices. Warning icon. |

**Unallocated warning banner (below cards, only shown when unallocated entries exist):**
- Info banner: "X payments not linked to any invoice. R Y in unmatched funds. Allocate them to clear outstanding balances."
- "Review" button (right side) → scrolls to or filters the table to show only unallocated entries

**Toolbar:**
Uses the full `AppTableToolbar` component with search, date range, filter, sort, saved views, and export functionality — same pattern as Billing page.

**Quick filter tabs:** All Entries (count) | Allocated (count) | Unallocated (count)

**Cashbook table columns:**

| Column | Content |
|---|---|
| DATE | Transaction date (e.g., "02 Apr 2026") |
| DESCRIPTION | Bank statement description (e.g., "EFT – S VAN DER MERWE LEVY APR") |
| TYPE | Badge: "Credit" (green outline) or "Debit" (red outline) |
| AMOUNT | Green with + prefix for credits ("+R 2 850"), red with - prefix for debits ("-R 12 500") |
| UNIT | Unit number if allocated (e.g., "A01"), "—" if unallocated |
| INVOICE | Invoice number if allocated (e.g., "INV-2026-0001"), "—" if unallocated |
| STATUS | Badge: "Allocated" (green outline) or "Unallocated" (amber outline) |
| ACTION | "Allocate" button (only shown for unallocated entries) — opens allocation workflow |

- Clicking any row → navigates to cashbook entry detail page (`/cashbook/{entryId}`)
- Button-based pagination (15 rows per page) with Previous/Next controls

> **Example — Understanding the cashbook table:**
> The admin downloads the bank statement for Crystal Mews' body corporate account. It shows:
> - 02 Apr: EFT – S VAN DER MERWE LEVY APR → +R 2,850 (Sarah paid her levy)
> - 03 Apr: EFT – L MOKOENA RENT APR → +R 9,500 (Lisa paid rent)
> - 06 Apr: SECURITY SERVICES – MARCH → -R 12,500 (body corporate paid the security company)
> - 07 Apr: EFT – UNKNOWN REF 8827 → +R 2,850 (someone paid but reference is unclear)
>
> The first two are credits that can be allocated to specific invoices. The security payment is a debit (expense) that stays unallocated until the full accounting module is built (Phase 2). The unknown payment needs the admin to investigate and manually allocate.

**Charts section (below table, 2-column layout):**

**Cash Flow** (left, ~50%):
- Vertical bar chart showing total credits vs total debits
- Two bars: Credits (green), Debits (red)
- Y-axis: amount scale

**Allocation Status** (right, ~50%):
- Donut/ring chart showing allocated vs unallocated percentage
- Segments: Allocated (green/navy), Unallocated (amber)
- Labels with percentages: "Allocated 63%", "Unallocated 38%"

**Add Entry modal:**
- Uses shared `AppModal`
- Title: "Add Cashbook Entry"
- Fields:
  - Estate (dropdown — select which estate this entry belongs to)
  - Date (date picker)
  - Type (dropdown): Credit (Received) | Debit (Paid Out)
  - Description (text input — as it appears on the bank statement)
  - Amount (number input)
  - Unit (optional dropdown — if known, pre-allocate to a unit)
  - Invoice Number (optional text input — if known, link to specific invoice)
- Buttons: "Cancel" (outline) | "Add Entry" (amber)
- On save: creates cashbook entry record. If unit and invoice provided, auto-allocates. Otherwise, entry is unallocated.

**Allocate button workflow** (for unallocated entries):
1. Click "Allocate" on an unallocated row → opens Allocation modal
2. Modal shows: entry details (date, description, amount) at top
3. Search/select a unit from the estate
4. System shows all outstanding invoices for that unit, grouped by charge type, with amount owing on each
5. Admin selects which invoice to allocate against
6. Charge type auto-tagged from the selected invoice
7. **If payment amount > invoice amount:** the system shows a split preview — "R2,850 will be allocated to INV-2026-0001. R150 will remain as unallocated credit on Unit A02." Admin confirms the split.
8. **If payment amount < invoice amount:** partial payment — the invoice moves to `partially_paid` status, and the full cashbook entry is allocated to it
9. **If payment amount = invoice amount:** exact match — invoice moves to `paid`, entry fully allocated
10. **If no matching invoice** (advance payment): admin tags the entry with a unit and optionally adds a note (e.g., "Advance — May levy"). Entry remains unallocated until a future invoice is created.
11. Confirm → entry status derived from `invoice_id` presence. Split entries created if applicable.

### 8.13 Cashbook Entry Detail Page

**Route:** `/cashbook/{entryId}` (e.g., `/cashbook/cb1`)

Accessed by clicking any cashbook entry in the cashbook table, or from unit detail "Payments Received" section.

**Header:**
- Back arrow (←) → returns to cashbook page
- "Cashbook Entry" (heading)
- Type badge: "Credit" (green) or "Debit" (red)
- Status badge: "Allocated" (green) or "Unallocated" (amber)
- Description as subtitle (e.g., "EFT – S VAN DER MERWE LEVY APR")
- "Edit Entry" button (top-right) → toggles the page into edit mode (inline editing, not a modal — based on prototype)

**View mode (default):**

**Left column:**

**Entry Details card:**
- DATE: transaction date
- AMOUNT: with + or - prefix, coloured (green for credit, red for debit)
- DESCRIPTION: bank statement text
- TYPE: badge "Credit (Received)" or "Debit (Paid Out)"
- ALLOCATION STATUS: badge "Allocated" or "Unallocated"

**Proof of Payment section:**
- "Proof of Payment" heading with paperclip icon
- "Upload" button (top-right of section) → opens file upload (drag-and-drop or browse)
- If proof uploaded: image/PDF preview displayed inline, file details below (filename, size, upload date), download icon and delete (trash) icon
- If no proof: empty state with paperclip icon, "No attachments" text, "Upload proof of payment to keep records complete" helper text
- Uploading a new file replaces the existing one
- Supports: PDF, JPG, PNG

**Change Log section:**
- Vertical timeline of all changes to this entry, ordered newest first
- Event types: "Entry created" (Created badge), "Updated entry details" (Details badge), "Uploaded proof of payment" (Attachment badge), "Allocated to invoice" (Allocation badge)
- Each entry shows: event title + category badge, detail bullets (field-level changes with old → new), changed by (person name), timestamp
- Clicking any change log entry → opens **Change Details modal** (same reusable pattern as unit detail change log — this is universal across the platform)

> **Example — Change log for a cashbook entry:**
> 1. Entry created — "Credit entry added for Crystal Mews Body Corporate, Amount: R 2 850" — Justin Mokoena, 2026-03-25T08:20:00
> 2. Updated entry details — "Description: 'EFT Payment' → 'EFT – S VAN DER MERWE LEVY APR', Amount: R 2 500 → R 2 850" — Thabo Ndlovu, 2026-03-30T09:00:00
> 3. Uploaded proof of payment — "proof_of_payment.pdf (245 KB)" — Lerato Pillay, 2026-04-01T14:30:00
> 4. Allocated to invoice — "Linked to INV-2026-0001, Unit: A01" — Justin Mokoena, 2026-04-02T10:15:00

**Right column:**

**Financial summary card:**
- Amount: large green (+) or red (-) text
- Status: badge

**Context card:**
- Estate: estate name (with building icon)
- Unit: unit number (if allocated, with hash icon)
- Invoice: invoice number (if allocated, with document icon) — clickable, navigates to invoice detail
- Date: transaction date (with calendar icon)

**"Allocate to Invoice" button** (amber, full-width, only shown when status = Unallocated):
- Opens the same allocation workflow as the "Allocate" button on the cashbook table

**Edit mode** (toggled by clicking "Edit Entry"):
- Header changes: "Edit Entry" button becomes "Cancel" (outline) + "Save" (amber with save icon)
- Entry Details card becomes editable: Date (date picker), Type (dropdown), Description (text input), Amount (number input), Unit (optional dropdown), Invoice Number (optional text input)
- Proof of Payment section: "Upload" button remains functional
- On save: updates record, logs all changes in Change Log with field-level diff

### 8.14 Age Analysis

**Route:** `/age-analysis`

- Per-estate ageing report
- Uses the full `AppTableToolbar` component with search, date range, filter, sort, saved views, and export
- Filter by charge type: All, or drill into any specific type (Levy, Rent, Water Recovery, etc.)
- Filter by estate: All estates or specific estate
- Single "Export" button (download icon + "Export") → opens the standard `AppExportModal` (see Section 8.10). Format options: CSV, Excel, PDF. Record count options: Current page, 25, 50, 100, 500, 1 000.

**Summary cards (top row — 6 cards):**

| Card | Value | Style |
|---|---|---|
| Current | Amount (e.g., "R 23 500") | Black text |
| 30 Days | Amount (e.g., "R 5 700") | Black text |
| 60 Days | Amount (e.g., "R 2 850") | Black text |
| 90 Days | Amount (e.g., "R 0") | Red text when zero |
| 120+ Days | Amount (e.g., "R 0") | Red text when zero |
| Total Outstanding | Amount (e.g., "R 32 050") | Red text, red border |

**Tab filter:** Owners (count) | Tenants (count) — splits data into two distinct views.

Each tab shows a table with data relevant to that group only. The summary cards above reflect totals for the selected tab (Owners or Tenants), not the combined total.

**Owners table columns:**

| Column | Content |
|---|---|
| NAME | Owner full name — **clickable link** → navigates to Owner Detail page (`/owners/{ownerId}`) |
| UNIT | Unit number (e.g., "A02") |
| TYPE | Charge type label (e.g., "Levy") |
| CURRENT | Current period amount |
| 30 DAYS | 30-day arrears amount |
| 60 DAYS | 60-day arrears amount. **Red text** when overdue. |
| 90 DAYS | 90-day arrears amount |
| 120+ DAYS | 120+ day arrears amount |
| TOTAL | Total outstanding per person — **bold** |

Dash (—) indicates no arrears for that bucket. Zero amounts for 90/120+ show as dashes.

**Tenants table columns:**

Same column structure as Owners table. Tenant name is a **clickable link** → navigates to Tenant Detail page (`/tenants/{tenantId}`). Type column shows "Rent" or other tenant-applicable charge types.

> **Example — Age analysis for Crystal Mews (Owners tab):**
>
> | NAME | UNIT | TYPE | CURRENT | 30 DAYS | 60 DAYS | 90 DAYS | 120+ | TOTAL |
> |---|---|---|---|---|---|---|---|---|
> | Michael Ndaba | A02 | Levy | R 2 850 | — | — | — | — | R 2 850 |
> | Johan Pretorius | A03 | Levy | R 2 850 | R 2 850 | — | — | — | R 5 700 |
> | Thandi Dlamini | A04 | Levy | R 2 850 | R 2 850 | R 2 850 | — | — | R 8 550 |
> | James Motsepe | B03 | Levy | R 1 600 | — | — | — | — | R 1 600 |
> | Anele Zulu | B04 | Levy | R 2 850 | — | — | — | — | R 2 850 |
>
> **Tenants tab:**
>
> | NAME | UNIT | TYPE | CURRENT | 30 DAYS | 60 DAYS | 90 DAYS | 120+ | TOTAL |
> |---|---|---|---|---|---|---|---|---|
> | Rachel Naidoo | B04 | Rent | R 10 500 | — | — | — | — | R 10 500 |
>
> Filtering to "Levy only" would show only the Owners tab data since these are all levy arrears. Filtering to "Rent only" would show only tenant arrears for rent charges.

**Charts section (below the table — 4 charts in a 2×2 grid):**

1. **Arrears by Ageing Bucket** (vertical bar chart)
   - X-axis: Current, 30 Days, 60 Days, 90 Days, 120+ Days
   - Y-axis: Rand amount (e.g., R 0k to R 24k)
   - Bars coloured by bucket (amber/gold for 30/60, red for 90/120+)
   - Shows the distribution of total arrears across time buckets

2. **Owners — Outstanding** (horizontal bar chart)
   - Y-axis: Owner surnames (e.g., Motsepe, Ndaba, Zulu, Pretorius, Dlamini)
   - X-axis: Rand amount
   - Sorted by highest arrears descending
   - Dark navy bars

3. **Tenants — Outstanding** (horizontal bar chart)
   - Y-axis: Tenant surnames (e.g., Naidoo)
   - X-axis: Rand amount
   - Amber/gold bars
   - Only shows tenants with outstanding balances

4. **Owner vs Tenant Split** (donut chart)
   - Shows percentage split of total arrears between owners and tenants
   - Legend: Owners (navy) | Tenants (amber)
   - Labels show percentage (e.g., "Owners 67%", "Tenants 33%")

### 8.14.1 Owner Detail Page

**Route:** `/owners/{ownerId}`

This page is structurally similar to the Tenant Detail page (Section 8.9) with the following differences:

- **Header** shows owner name, role as "Owner", email, phone, and the unit(s) they own
- **Financial context** is levy-focused: outstanding levies, levy payment history, levy arrears
- **Unit ownership section** lists all units owned by this person (an owner may own multiple units across estates)
- **Invoices tab** shows all levy and owner-applicable charge invoices for this owner
- **Statements tab** shows consolidated statements sent to this owner
- **Payment History** shows all cashbook entries allocated to this owner's invoices
- **Age Analysis summary card** shows this owner's personal arrears breakdown (Current / 30 / 60 / 90 / 120+)
- **Communication log** (emails sent, messages) — same as tenant detail

> The Owner Detail page and Tenant Detail page share the same layout component. The difference is contextual: owners see levy-related financials, tenants see rent-related financials. Both show invoices, statements, payment history, and communication logs for that person.

### 8.15 Users

**Route:** `/users`

**Page header:**
- "Users" (large heading)
- Subtitle: "Manage platform users and their access levels"
- **"+ Invite User" button** (top-right, amber with plus icon): opens Invite User modal

**Summary cards (top row — 4 cards):**

| Card | Value | Label |
|---|---|---|
| Total Users | Count (e.g., "10") | "Total Users" |
| Internal Staff | Count (e.g., "5") | "Internal Staff" |
| External Users | Count (e.g., "5") | "External Users" |
| Active Now | Count (e.g., "8") | "Active Now" |

**Tab filter:** All Users (count) | Internal (count) | External (count)

- **Internal** tab shows only company staff: Admin, Portfolio Manager, Financial Controller, Portfolio Assistant
- **External** tab shows only client-facing users: Trustee / Director, Owner, Tenant, Contractor

**Search bar:** "Search users..." — right-aligned, filters user table.

**User table columns:**

| Column | Content |
|---|---|
| USER | Avatar (initials circle, coloured background), full name (bold), email (with envelope icon, muted), phone (with phone icon, muted) — all on same row |
| ROLE | Role badge with icon: Admin (shield icon), Portfolio Manager (building icon), Financial Controller (building icon), Portfolio Assistant (building icon), Trustee / Director (circle icon), Owner (person icon), Tenant (person icon), Contractor (tool icon) |
| ASSIGNED ESTATES | Estate name(s) with "+N more" overflow (e.g., "Crystal Mews BC +1 more", "Crystal Mews BC +2 more"). "—" if not assigned (e.g., Admin role) |
| STATUS | Active (green badge), Invited (amber/outline badge), Inactive (gray badge) |
| LAST LOGIN | Date (e.g., "03 Apr 2026"). "—" if never logged in (invited users) |
| Actions | "..." three-dot menu (edit, deactivate, resend invite, etc.) |

> **Example data — All Users tab (10 users):**
>
> **Internal (5):** Justin Mokoena (Admin, Active), Thabo Ndlovu (Portfolio Manager, Active), Lerato Pillay (Financial Controller, Active), Naledi Khumalo (Portfolio Assistant, Active), Sipho Dlamini (Portfolio Manager, Invited — no assigned estates, never logged in)
>
> **External (5):** David Botha (Trustee / Director, Crystal Mews BC, Active), Michael Ndaba (Owner, Crystal Mews BC, Active), Lisa Mokoena (Tenant, Crystal Mews BC, Active), ProFix Maintenance (Contractor, Crystal Mews BC +1 more, Active), Susan van der Merwe (Owner, Sunrise Industrial Park, Inactive)

**Invite User modal:**

Uses shared `AppModal`. Title: "Invite User".

Fields:
- **Full Name** (text input, placeholder: "e.g. John Smith")
- **Email Address** (text input, placeholder: "e.g. john@email.com")
- **Phone Number** (text input, placeholder: "e.g. +27 82 000 0000")
- **User Category** (dropdown: "Select category")
  - Options: "Internal (Bold Mark Staff)" | "External (Client)"
  - This determines which roles are available in the Role dropdown below
- **Role** (dropdown: "Select role")
  - If Internal selected: Admin, Portfolio Manager, Financial Controller, Portfolio Assistant
  - If External selected: Trustee / Director, Owner, Tenant, Contractor
- **Assigned Estates** (multi-select from existing estates) — only shown after role is selected, not applicable for Admin role

Buttons: "Cancel" (outline) | "Send Invitation" (amber)

On save: creates user record with "Invited" status, sends invite email via Resend.

### 8.16 Settings

**Route:** `/settings`

**Page header:**
- "Settings" (large heading)
- Subtitle: "Manage your account and company configuration"

**Tab navigation:** Account (person icon) | Company (building icon)

#### 8.16.1 Account Tab

**Route:** `/settings` (default tab)

**Profile card:**
- Section heading: "Profile" (with person icon)
- Fields (2-column grid):
  - Full Name (text input, e.g., "Justin Sobhee")
  - Email Address (text input, e.g., "justin@boldmarkprop.co.za")
  - Role (text input, **read-only/disabled**, e.g., "Company Admin")
  - Phone (text input, e.g., "+27 82 555 1234")
- "Update Profile" button (amber)

**Change Password card:**
- Section heading: "Change Password" (with key icon)
- Fields (single column, half-width):
  - Current Password (password input)
  - New Password (password input)
  - Confirm New Password (password input)
- "Change Password" button (amber)

**Security card:**
- Section heading: "Security" (with shield icon)
- **Two-Factor Authentication** — description: "Add an extra layer of security to your account". Action: "Enable" button (outline, right-aligned)
- **Active Sessions** — description: "Manage your active login sessions". Action: "View Sessions" button (outline, right-aligned)

#### 8.16.2 Company Tab

**Route:** `/settings` (Company tab selected)

**Company Settings card:**
- Section heading: "Company Settings"
- Fields (2-column grid):
  - Company Name (text input, e.g., "Bold Mark Properties") — used as the sidebar company name
  - Company Slogan (text input, e.g., "Property Management") — shown below the company name in the sidebar
  - Contact Email (text input, e.g., "info@boldmarkprop.co.za")
  - Country (dropdown, e.g., "South Africa (ZA)")
  - Phone (text input, e.g., "+27 10 442 0012")
  - Currency (dropdown with globe icon, e.g., "R — South African Rand (ZAR)")

> **Important:** The Company Name and Company Slogan fields directly control the text displayed at the top of the sidebar navigation. Changing these values updates the sidebar branding across the entire application for all users in the organisation.

**Branding card:**
- Section heading: "Branding"
- **Primary Color** — colour swatch preview + hex input (e.g., "#1F3A5C") + reset button (circular arrow icon)
- **Secondary Color** — colour swatch preview + hex input (e.g., "#D89B4B") + reset button
- "Save Settings" button (amber, right-aligned)

> **Branding colours** control the visual theme of the entire dashboard. The **primary colour** sets the sidebar background, header elements, and primary UI chrome. The **secondary colour** sets button backgrounds (amber/gold), active states, highlights, and accent elements. Changing these colours re-themes the application in real-time for all users in the organisation. Default values: Primary = #1F3A5C (dark navy), Secondary = #D89B4B (amber/gold).

**Charge Types card:**
- Section heading: "Charge Types"
- **"+ Add Charge Type" button** (amber with plus icon, top-right of card)
- Charge types table columns:

| Column | Content |
|---|---|
| NAME | Charge type name (bold) + description below (muted). Lock icon (🔒) next to system defaults (Levy, Rent) |
| CODE | Uppercase code (e.g., "LEVY", "RENT", "SPECIAL_LEVY", "WATER_RECOVERY") |
| APPLIES TO | Badge: "Owner" (amber/outline) / "Tenant" (blue/outline) / "Either" (gray/outline) |
| RECURRING | "Monthly" or "Ad-hoc" |
| STATUS | "Active" (green badge) / "Inactive" (gray badge) |
| ACTIONS | Edit (pencil icon) + Delete (trash icon). System defaults (Levy, Rent) show only edit — no delete. |

> **System defaults (Levy, Rent):** Cannot be deleted. Shown with lock icon. Can only be edited (e.g., change description). Levy always applies to Owner. Rent always applies to Tenant.

**Charge types visible in prototype:**

| Name | Code | Applies To | Recurring | Status |
|---|---|---|---|---|
| Levy 🔒 | LEVY | Owner | Monthly | Active |
| Rent 🔒 | RENT | Tenant | Monthly | Active |
| Special Levy | SPECIAL_LEVY | Owner | Ad-hoc | Active |
| Water Recovery | WATER_RECOVERY | Either | Monthly | Active |
| Electricity Recovery | ELECTRICITY_RECOVERY | Either | Monthly | Active |
| Late Payment Interest | LATE_INTEREST | Either | Ad-hoc | Active |
| Late Payment Penalty | LATE_PENALTY | Either | Ad-hoc | Active |
| Damage Deposit | DAMAGE_DEPOSIT | Tenant | Ad-hoc | Active |
| Parking Rental | PARKING_RENTAL | Either | Monthly | Active |
| Pet Levy | PET_LEVY | Either | Monthly | Active |
| Insurance Excess | INSURANCE_EXCESS | Owner | Ad-hoc | Inactive |
| Legal Recovery | LEGAL_RECOVERY | Either | Ad-hoc | Inactive |

**Add Custom Charge Type modal:**

Uses shared `AppModal`. Title: "Add Custom Charge Type".

Fields:
- **Name** (text input, placeholder: "e.g. Generator Fee")
- **Code** (text input, placeholder: "e.g. GENERATOR_FEE") — auto-generated from name in uppercase with underscores, but editable
- **Description** (textarea, placeholder: "Brief description...")
- **Applies To** (dropdown): Owner | Tenant | Either
- **Recurring?** (dropdown): "Yes — Monthly" | "No — Ad-hoc"

Buttons: "Cancel" (outline) | "Save" (amber)

On save: charge type is added to the organisation's charge type list. It becomes available in billing dropdowns (Run Billing, Ad-Hoc Billing), age analysis filters, and cashbook allocation across all estates.

> **Charge types flow:** Charge types are created in Settings → Company → Charge Types. They then appear as options when running billing, creating ad-hoc invoices, filtering the age analysis, and allocating cashbook entries. Each estate can enable/disable specific charge types from this master list via the estate configuration.

---

## 9. Business Rules

1. An estate belongs to exactly one organisation
2. A unit belongs to exactly one estate and always has exactly one owner
3. A unit may have zero or one active tenant at a time
4. Changing occupancy type from `tenant_occupied` archives the tenant record (soft-delete)
5. Charge types are scoped per organisation. Levy and Rent are system defaults and cannot be deleted.
6. Each estate has a configurable set of active charge types, auto-initialised from the estate type
7. Each unit can have per-unit recurring charge configurations (amount and active status) for charges like parking, gym, pet levy
8. Invoices reference a `charge_type_id`, not a hardcoded enum
9. The billing engine generates invoices based on estate charge config, unit occupancy, and unit charge config
10. Levy invoices are always billed to the owner. Rent invoices always to the tenant. For charge types with `applies_to = either`, the billing engine bills the current occupant: tenant if tenant-occupied, owner otherwise (including vacant units).
11. Cashbook entries inherit `charge_type_id` from the invoice on allocation. Allocation status is derived from `invoice_id` presence — no separate boolean.
12. Unallocated cashbook entries remain in holding queue until matched. When a payment exceeds an invoice amount, the system splits the entry: allocated portion links to the invoice, remainder becomes a new unallocated entry (credit on account) linked to the same unit.
13. Age analysis is computed in real-time, filterable by charge type
14. Invoice payment status: `unpaid` → `partially_paid` → `paid`. Overdue is set by a scheduled job when `due_date` passes and status is still `unpaid` or `partially_paid`. Email dispatch is tracked separately via `sent_at` timestamp — null means not yet emailed, populated means dispatched. These are two independent tracks; an invoice can be `unpaid` and not yet emailed, or `overdue` and already opened. Never conflate them.
15. Levy amounts default to estate level, overridable per unit. Rent amounts are always per-unit.
16. Duplicate invoice prevention: same unit + charge_type + billing_period = blocked
17. Tenant records are never hard-deleted — archived for audit and history
18. **Sectional Title estates do not support tenant occupancy.** In a `sectional_title` estate, every unit is owned by an individual owner who pays levies to the body corporate. The system does not track tenants or collect rent for sectional title estates — that is a separate rental-management concern only applicable to `residential_rental`, `commercial_rental`, and `mixed` estate types. Therefore:
    - The occupancy type for units in a `sectional_title` estate is restricted to **`owner_occupied`** or **`vacant`** only. `tenant_occupied` is **never valid** for sectional title.
    - The Add Unit and Edit Unit forms **must not show** tenant fields, the tenant toggle, or rent amount for sectional title estates.
    - The backend (`CreateUnitRequest`, `UpdateUnitRequest`) **must reject** any request that sets `occupancy_type = tenant_occupied` or includes `tenant` data for a unit belonging to a `sectional_title` estate.
    - This rule is enforced at both the frontend (UI hides options) and the backend (validation rejects invalid payloads) to prevent data corruption under any circumstance.

---

## 10. Billing Engine Logic

### 10.1 Invoice Generation (Run Billing)

When "Run Billing" is triggered for an estate and billing period:

1. **Get all active units** for the estate (`status = active`)
2. **For each unit, determine applicable invoices** based on:
   - (a) Estate charge type configuration — which charge types are enabled for this estate
   - (b) Unit occupancy type — determines owner vs tenant billing
   - (c) Unit charge config (UnitChargeConfig) — which per-unit recurring charges are active and at what amount
3. **Levy** (if enabled on estate): generate invoice to the unit's owner. Amount = `unit.levy_override` or `estate.default_levy_amount`. Applies to all units regardless of occupancy (owners always owe levies).
4. **Rent** (if enabled on estate AND `unit.occupancy_type = tenant_occupied` AND active tenant exists): generate invoice to the tenant. Amount = `unit.rent_amount`.
5. **Per-unit recurring charges** (parking, pet levy, gym, etc.): check `UnitChargeConfig`. If active, generate invoice to the person specified by `charge_type.applies_to`:
   - `applies_to = owner` → always invoice the owner
   - `applies_to = tenant` → invoice the tenant (skip if no active tenant)
   - `applies_to = either` → invoice the **current occupant**: tenant if `unit.occupancy_type = tenant_occupied` and active tenant exists, otherwise invoice the owner. This means the billing target for "Either" charges flips automatically when occupancy changes — the subscription stays on the unit, but the recipient follows whoever is living there. For vacant units, the owner is billed.
6. **Duplicate prevention** — skip if invoice already exists for same unit + charge_type + billing_period. Show warning in preview.
7. **Present preview** to admin. Admin confirms.
8. **Generate individual invoice records** in the database (one per charge type per unit).
9. **Generate consolidated statement PDFs** — group all invoices for the same person in the same period into one branded PDF statement. The statement shows all line items, previous balance, payments received, and total outstanding.
10. **Email statements** via Resend to each person. Each person receives ONE email with ONE statement PDF, regardless of how many invoice records were created for them.

> **Key rule: Invoices are atomic (one charge type each). Statements are consolidated (all charges for one person).**
> The database stores invoices. The person receives statements. The cashbook allocates against invoices. The age analysis reports on invoices. But the human-facing document is always the statement.

### 10.2 Ad-Hoc Billing

For non-recurring charge types (special levy, insurance excess, moving fee, access card fee):

1. Admin selects estate, charge type (non-recurring only), units (all or specific), and amount
2. Preview shows all invoices to be generated
3. On confirm: individual invoice records created, statement PDFs generated, emails sent
4. Ad-hoc invoices appear in the billing table alongside regular invoices — no distinction in how they're tracked

### 10.3 Billing Schedule (Automatic Billing)

The system supports fully automatic billing. When enabled, invoices are generated and emailed without admin intervention on the scheduled date.

**Two-tier configuration:**

1. **Company default** (Settings → Billing Schedule):
   - Default levy billing day (e.g., 1st of each month)
   - Default rent billing day (e.g., 1st of each month)
   - Default billing time (e.g., 08:00)
   - Auto-send toggle (on/off)

2. **Estate override** (Estate detail → Billing Schedule):
   - Override billing day for levies at this estate
   - Override billing day for rent at this estate
   - Override billing time
   - Override auto-send toggle (can disable auto-send for specific estates)
   - If no override set, estate inherits company defaults

**How automatic billing works:**
- A scheduled Laravel job runs daily. It checks which estates have billing due today based on their effective schedule (estate override or company default).
- For each estate due today, it executes the same billing engine logic as "Run Billing" (Steps 1–10 above).
- If auto-send is enabled: invoices generated, statements emailed automatically.
- If auto-send is disabled: invoices generated as **drafts**. Admin must manually review and dispatch.

### 10.4 Invoice vs Statement — Data Model Implications

**Invoice** (stored in database — the `invoices` table defined in Section 6.7):
- One record per charge type per unit per billing period
- Has a payment status (`unpaid`/`partially_paid`/`paid`/`overdue`) — never `draft` or `sent`
- Has a `sent_at` timestamp (null = not yet emailed, populated = dispatched) — separate from payment status
- Cashbook entries allocate against specific invoices
- Age analysis computed from invoice records

**Statement** (generated document — NOT a separate database table):
- A PDF grouping all invoices for one person in one period
- Shows: all line items (charge type + amount), previous balance brought forward, payments received this period, total outstanding
- Generated on-demand when billing runs or when admin clicks "Generate Statement"
- Stored in AWS S3 as a PDF file, linked to the person and period
- This is what the owner/tenant actually receives via email

---

## 11. Feature Modules — Full System Spec

> Phase 1 focuses on modules 11.1–11.6. Later modules are specified here for completeness and to inform architectural decisions from day one.

### 11.1 Authentication & Multi-tenancy
- Laravel Passport OAuth2 with personal access tokens
- Email/password login
- Password reset via Resend
- Remember me / session management
- Tenant resolution (subdomain or header-based)
- Per-tenant user management
- Invite-based user onboarding (email invite via Resend)
- 2FA (future)

### 11.2 Dashboard

**Portfolio Dashboard (Company Admin / Portfolio Manager view):**
- Total estates managed
- Total units under management
- **Total arrears** — highlighted top-level KPI, broken down by charge type
- Compliance status: % compliant estates
- Open job cards / tasks
- Estates with critical issues (red flags)
- Recent activity feed

**Estate Dashboard (per estate):**
- Total outstanding by charge type
- Occupancy breakdown (owner-occupied, tenanted, vacant)
- Recent billing runs
- Unallocated cashbook entries count
- Payment collection this month vs last month

### 11.3 Estates & Units (see Sections 6.3–6.6 for data model, Section 8.4–8.5 for UI)

### 11.4 Billing & Invoicing (see Section 10 for engine logic, Section 8.6 for UI)

### 11.5 Cashbook (see Section 6.8 for data model, Section 8.7 for UI)

### 11.6 Age Analysis & Reporting (see Section 6.9, Section 8.8 for UI)

### 11.7 Debt Management (Arrears & Collections) — Phase 2+

**THIS WILL BE THE HIGHEST PRIORITY MODULE AFTER PHASE 1.**

#### Arrears Tracking
- Real-time arrears balance per unit, per estate, and portfolio-wide
- Aging analysis: current, 30, 60, 90+ days (built into Phase 1 age analysis)
- Automated arrears status flag on unit accounts

#### Debt Collection Workflow (multi-stage per debtor)
1. **Reminder** — automated friendly reminder (configurable days after due date)
2. **First Letter of Demand** — formal letter with outstanding amount
3. **Second Letter of Demand** — escalated notice
4. **Hand Over to Attorney** — flag for legal action, track attorney reference
5. **Payment Arrangement** — record agreed payment plan, track adherence
6. **Write Off** — formal write-off with approval workflow

Each stage: tracked with timestamps, all communication sent via Resend and logged, notes captured per debtor interaction.

#### Automated Fees
- Admin fee billing when account falls into arrears (configurable per estate)
- Interest calculation on overdue amounts (configurable rate) — uses the Late Payment Interest and Late Payment Penalty charge types
- All fees auto-generated and added to unit account

#### Payment Arrangements
- Record arrangements with schedule
- Track adherence vs. arrangement
- Automated reminders for upcoming payments
- Flag breached arrangements

#### Debt Dashboard
- Total outstanding per stage
- Debtors list with balance and stage
- Trending chart (arrears growing or shrinking)
- % of billing book in arrears
- Collections recovered this month/quarter/year

#### Letters & Templates
- Configurable per-estate letter templates
- Merge fields: owner/tenant name, unit number, balance, due dates, amount, company details
- PDF generation for letters of demand
- Bulk dispatch via Resend

### 11.8 Financial Accounting — Phase 2+

South African community scheme accounting requires:
- Separate **Admin Fund** and **Reserve Fund** books
- Double-entry accounting
- Trial balance, income & expenditure statement, cash flow report
- Budget vs. Actual comparison
- Pre-built SA community scheme chart of accounts
- Supplier (creditor) management with trustee authorization workflow
- Bulk payment file generation (EFT batch)
- CSOS levy calculation, raising, collection, and annual return support

### 11.9 Bank Reconciliation — Phase 2+

- Import bank statements (CSV/Excel) for all major SA banks (FNB, ABSA, Standard Bank, Nedbank, Capitec)
- Auto-match statement lines to system transactions
- Manual matching interface for unmatched items
- Flag and investigate exceptions
- Reconciliation report

### 11.10 Compliance Planner — Phase 2+

- Compliance calendar: upcoming tasks/deadlines by estate or portfolio-wide
- Items: AGM date, audit deadline, CSOS return, insurance renewal, trustee meetings, POPIA obligations
- Status: Compliant / Due Soon / Non-compliant / Action Required
- Color-coded dashboard
- Automated reminders
- Global compliance percentage KPI

### 11.11 Task / Job Card Management — Phase 2+

- Tasks linked to estate, unit, or contractor
- Types: maintenance, administrative, compliance, owner query, general
- Priority: low, medium, high, urgent
- Status workflow: open → in progress → awaiting response → closed
- Assignment, due dates, comments, file attachments
- 24-hour SLA tracking
- Portfolio-wide dashboard

### 11.12 Communications — Phase 2+

- Bulk email via Resend (all owners, all trustees, all debtors, selected list, portfolio-wide)
- Email templates with merge fields
- Email archive per estate and per unit
- SMS support (third-party gateway TBD)
- In-app notification center
- Communication log per unit

### 11.13 Document Management — Phase 2+

- Upload per estate, per unit, or globally to AWS S3
- Categories: meeting minutes, CSOS, insurance, financials, contracts, compliance, correspondence
- Role-based visibility
- Version management
- Bulk upload, in-browser preview, download

### 11.14 Trustee Portal — Phase 2+

- Secure login
- Real-time financial overview (bank balance, fund status, arrears)
- Payment approval workflow
- Task dashboard
- Document access
- Compliance status
- Meeting schedule

### 11.15 Owner Portal — Phase 2+

- Secure login (invite via email)
- Account balance and statement view (PDF download)
- Community notices
- Submit maintenance request (logged as task)
- View community documents

### 11.16 Transfers & Clearance Certificates — Phase 2+

4-step process: Initiate → Account Settlement → Generate Clearance Certificate PDF → Complete Transfer (update ownership, pro-rata billing)

### 11.17 Warnings & Penalties — Phase 2+

- Formal warnings for conduct rule breaches
- Escalation: verbal → written → fine
- Penalty invoice auto-generated and linked to owner account via charge type

### 11.18 Meeting Management (AGM/SGM) — Phase 3+

- Full MeetingSpace equivalent: invitations, RSVP, proxy management, quorum calculation, PQ-weighted voting, attendance register, minutes capture, post-meeting task generation

### 11.19 SaaS Onboarding & Company Management — Phase 2+

- Super Admin panel for all tenant companies
- Tenant creation, plan assignment, feature flags
- Per-tenant branding (logo, colour)
- Usage statistics
- Subscription billing (future)

---

## 12. SaaS Business Model

This is NOT a single-client application. It is a multi-tenant SaaS platform.

### Tenancy Hierarchy

```
Super Admin (Optimum Quality)
└── Tenant: Bold Mark Properties
    ├── Estate: Crystal Mews BC (47 units)
    ├── Estate: King Arthur BC (32 units)
    ├── Estate: Lyndhurst Estate (28 units)
    └── Estate: [Botswana properties]

└── Tenant: Other Managing Agent (future reseller)
    └── ...
```

### Multi-tenancy Rules

- **Complete data isolation** between tenants — no tenant can ever see another tenant's data
- Each tenant has their own branding (logo, primary color, company name)
- Tenant onboarding via Super Admin panel
- Tenant-level feature flags (enable/disable features per plan tier)
- Unlimited estates, units, and users within plan limits
- Separate AWS S3 storage paths per tenant

### Pricing (to be defined with client)

- Per-unit pricing or flat monthly fees per tier
- Feature-gated plans (basic / professional / enterprise)
- Free trial / onboarding period
- Bold Mark controls pricing when reselling to other companies

---

## 13. User Roles & Permissions

Using Spatie Laravel Permission with the following role hierarchy:

### System-Level Roles

| Role | Scope | Description |
|---|---|---|
| `super-admin` | Global | Optimum Quality — full system access |
| `company-admin` | Tenant | Managing agent company administrator |
| `portfolio-manager` | Tenant | Manages assigned estates |
| `financial-controller` | Tenant | Finance-focused access |
| `portfolio-assistant` | Tenant | Operational access |

### External Roles

| Role | Scope | Description |
|---|---|---|
| `trustee` | Estate | Trustee/director of a specific scheme |
| `owner` | Unit | Unit owner — view own account |
| `tenant` | Unit | Occupant (Phase 2+) |
| `contractor` | System | Maintenance provider (Phase 3+) |

### Key Permissions

- `view-financials`, `manage-financials`
- `view-levies`, `manage-levies`, `approve-levies`
- `view-debt`, `manage-debt`, `approve-debt-actions`
- `view-compliance`, `manage-compliance`
- `view-maintenance`, `manage-maintenance`, `assign-contractors`
- `manage-users`, `manage-estates`, `manage-tenants`
- `manage-charge-types` — create/edit/delete custom charge types
- `view-reports`, `export-reports`
- `send-communications`
- `manage-documents`
- `approve-payments` (trustee permission)

---

## 14. Tech Stack

### Backend

| Technology | Version | Purpose |
|---|---|---|
| Laravel | 13.x | API backend, business logic, queue processing |
| PHP | 8.3+ | Runtime |
| Laravel Passport | Latest | OAuth2 API authentication, token management |
| Spatie Laravel Permission | Latest | Role-based access control (RBAC) |
| Supabase | N/A | PostgreSQL database, realtime (DB-level) |
| AWS S3 | N/A | File and document storage |
| Resend | Latest | Transactional email delivery (invoices, statements, arrears notices, bulk comms) |
| Pusher | Latest | Real-time notifications and live updates |
| Laravel Queues | Built-in | Background jobs (billing generation, bulk email, PDF generation, etc.) |

### Frontend

| Technology | Version | Purpose |
|---|---|---|
| Vue 3 | Latest stable | Frontend SPA framework |
| Vite | Latest | Build tooling and dev server |
| Tailwind CSS | 4.x | Utility-first CSS framework |
| Pinia | Latest | State management |
| Axios | Latest | HTTP client for API calls |
| Laravel Echo + Pusher JS | Latest | Real-time notification client |
| Chart library | chart.js/vue-chartjs or ApexCharts | Financial dashboards |
| vue-router | Latest | Client-side routing |
| @vueuse/core | Latest | Utility composables |
| @headlessui/vue | Latest | Accessible UI components |
| date-fns or dayjs | Latest | Date manipulation |

### Key Laravel Packages

- `laravel/passport` — OAuth2 API authentication
- `spatie/laravel-permission` — roles & permissions
- `spatie/laravel-media-library` — document/file management
- `maatwebsite/excel` — Excel/CSV import and export (unit uploads, bank statement imports, report exports)
- `barryvdh/laravel-dompdf` or `spatie/browsershot` — PDF generation (invoices, statements, age analysis, letters of demand)
- `laravel/horizon` — queue monitoring
- `resend/laravel` — transactional email via Resend
- `league/flysystem-aws-s3-v3` — AWS S3 file storage driver
- `pusher/pusher-php-server` — real-time broadcasting server-side
- `pestphp/pest` + `pestphp/pest-plugin-laravel` — testing framework (all tests use PEST, not PHPUnit)

### Testing Strategy (PEST)

All backend tests use **PEST** (Laravel PEST plugin). PHPUnit is not used directly.

| Type | Location | Purpose |
|---|---|---|
| Unit | `tests/Unit/` | Pure logic — billing calculations, age analysis computation, charge type resolution, levy apportionment, PQ calculations |
| Feature | `tests/Feature/` | Full API endpoint tests — HTTP requests, auth, responses, DB state |
| Integration | `tests/Feature/Integration/` | Multi-tenancy isolation — ensure tenant A cannot access tenant B data |

**Key testing rules:**
- Every API endpoint must have a corresponding feature test
- All financial calculation logic must have unit tests (billing engine, aging, interest, charge type resolution)
- Multi-tenancy isolation must be tested explicitly — critical security requirement
- Use PEST's `actingAs()` with different role fixtures for permission boundary testing
- Tests run against a dedicated test database (separate from dev Supabase)
- Use `RefreshDatabase` trait on all feature tests

**Test naming convention:**
```
it('calculates levy arrears correctly for 90-day overdue account')
it('prevents portfolio manager from accessing another tenant estate')
it('generates correct billing run for mixed-occupancy estate with 5 charge types')
it('auto-tags charge type when allocating cashbook entry to invoice')
```

### Seeding Strategy

Seeds are split into **system seeds** (always run) and **demo seeds** (dev/staging only).

#### Reset & seed command (dev/staging)

```bash
php artisan migrate:fresh --seed
```

This is the **single command** to fully reset and reseed the database in local and staging environments. `DatabaseSeeder` automatically calls `DemoSeeder` when `APP_ENV` is not `production` — no second command needed.

**Never run `migrate:fresh --seed` in production.** Production runs only `php artisan db:seed` (system seeds only).

#### Seeder structure

**`DatabaseSeeder` (entry point — `php artisan db:seed`):**

| Seeder | Environment | Purpose |
|---|---|---|
| `RolesAndPermissionsSeeder` | All | Create all Spatie roles and permissions |
| `SuperAdminSeeder` | All | Create the Optimum Quality super admin account |
| `DemoSeeder` | Non-production only | Orchestrates all demo data seeders (called automatically) |

**`DemoSeeder` (called automatically in non-production — never run in production):**

| Seeder | Data Created |
|---|---|
| `DemoTenantSeeder` | 1 tenant company: "Bold Mark Properties" (slug: `boldmark`) |
| `DefaultChargeTypesSeeder` | 24 default charge types (2 locked + 22 presets) for the demo tenant |
| `DemoUsersSeeder` | Company admin, portfolio manager, financial controller, portfolio assistant |

**Demo Account Credentials (consistent across resets — all passwords: `password`):**

| Role | Email | Name |
|---|---|---|
| Company Admin | `admin@demo.boldmark.test` | Justin Sobhee |
| Portfolio Manager | `pm@demo.boldmark.test` | Thabo Ndlovu |
| Financial Controller | `fc@demo.boldmark.test` | Lerato Pillay |
| Portfolio Assistant | `pa@demo.boldmark.test` | Naledi Khumalo |

> **Start with Company Admin** (`admin@demo.boldmark.test` / `password`) — this account has full access to all features.

---

## 15. Architecture Overview

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
│    Multi-tenancy middleware (tenant_id scoping)   │
│    Queue workers (billing, bulk email, PDFs)     │
└──────────┬───────────────────┬───────────────────┘
           │                   │
           ▼                   ▼
┌──────────────────┐  ┌──────────────┐  ┌─────────────┐
│   Supabase       │  │   AWS S3     │  │   Pusher    │
│   PostgreSQL DB  │  │   Documents  │  │   Realtime  │
└──────────────────┘  │   Invoices   │  └─────────────┘
                      │   Files      │
                      └──────────────┘
                      ┌──────────────┐
                      │   Resend     │
                      │   Email      │
                      └──────────────┘
```

### Multi-tenancy Implementation

- `tenant_id` column on ALL tenant-scoped models (organisations, estates, units, owners, tenants, invoices, cashbook entries, charge types, documents, tasks, etc.)
- Global scope middleware automatically filters all queries by the authenticated tenant
- Tenant resolution via subdomain (e.g., `boldmark.oursystem.com`) or API header
- Separate storage paths per tenant in AWS S3
- Complete data isolation — no tenant can ever see another tenant's data
- Per-tenant branding: logo, primary colour, company name
- Tenant-level feature flags for plan-gated functionality

### API Design

- RESTful API: `/api/v1/...`
- JSON responses with consistent envelope: `{ data, meta, errors }`
- Laravel Passport personal access tokens
- Rate limiting per tenant
- All endpoints require authentication except public-facing pages

### Project Structure

```
bold-mark-properties-system/
├── api/                    # Laravel 13 backend
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Models/
│   │   ├── Services/       # BillingService, CashbookService, AgeAnalysisService, etc.
│   │   ├── Jobs/           # GenerateInvoicesJob, BulkEmailJob, PdfGenerationJob
│   │   └── ...
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/api.php
│   └── tests/
│       ├── Unit/           # Billing calculations, ageing, charge type logic
│       ├── Feature/        # API endpoint tests
│       └── Feature/Integration/  # Multi-tenancy isolation
├── web/                    # Vue 3 frontend
│   ├── src/
│   │   ├── components/
│   │   │   └── common/     # AppButton, AppInput, AppCard, AppModal, AppBadge, etc.
│   │   ├── pages/
│   │   ├── composables/
│   │   ├── stores/         # Pinia stores
│   │   ├── router/
│   │   └── ...
│   ├── vite.config.ts
│   └── ...
├── CLAUDE.md               # This file
└── .gitignore
```

---

## 16. Design & Brand Guidelines

### Bold Mark Properties Brand Colors

Extracted directly from boldmarkprop.co.za — use these exact values everywhere.

| Color | Hex | Usage |
|---|---|---|
| Navy (Primary) | `#1F3A5C` | Backgrounds, headers, sidebar, primary UI elements |
| Navy Light | `#2D4A70` | Hover states, gradient layers |
| Navy Dark | `#152B47` | Active states, deep backgrounds |
| Amber (Accent) | `#D89B4B` | CTAs, highlights, active nav, accent lines, focus rings |
| Amber Light | `#E8B86B` | Hover tints on amber elements |
| Amber Dark | `#C87B33` | Hover state for amber buttons |
| Background | `#F8FBFF` | Page backgrounds |
| Foreground | `#1E2740` | Body text, headings |
| Muted | `#EDEFF5` | Table alternating rows, disabled backgrounds |
| Muted Text | `#717B99` | Secondary text, placeholders, hints |
| Border | `#DCDEE8` | Input borders, dividers, card borders |
| Success Green | `#22c55e` | Compliant status, positive amounts, paid badges |
| Warning Amber | `#D89B4B` | Partial payment, due soon |
| Danger Red | `#F75A68` | Non-compliant, arrears, errors, deficits, overdue |

### Typography

| Role | Font | Notes |
|---|---|---|
| Headings (h1–h4, section titles) | `DM Serif Display` | Serif, elegant — loaded via Google Fonts |
| Body, labels, buttons, UI | `DM Sans` | Clean sans-serif — variable weight |

### Reusable Component Library

**RULE: All generic UI components live in `web/src/components/common/` and must always be reused — never rebuild inline.**

| Component | File | Use For |
|---|---|---|
| `AppButton` | `common/AppButton.vue` | All buttons — variants: `primary` (amber), `secondary` (navy), `outline`, `ghost`, `danger` |
| `AppInput` | `common/AppInput.vue` | All text/email/password inputs — includes label, error, hint |
| `AppCard` | `common/AppCard.vue` | White content cards with shadow |
| `AppModal` | `common/AppModal.vue` | All dialogs and overlays |
| `AppBadge` | `common/AppBadge.vue` | Status pills — variants: `default`, `success`, `warning`, `danger`, `info` |
| `AppTooltip` | `common/AppTooltip.vue` | Hover tooltips |
| `AppAlert` | `common/AppAlert.vue` | Inline alerts/banners |

### UI Rules

- Use Tailwind 4 utility classes — no custom CSS unless absolutely necessary
- **Always import from `@/components/common/`** — never rebuild inline
- Tables for financial data: sortable columns, exportable
- Monetary amounts: `P` prefix for Botswana Pula, `R` prefix for South African Rand, formatted with commas (e.g., `P 1,500.00`, `R 141,666.64`)
- Negative amounts in red (`#F75A68`)
- Dates: South African format `21 January 2021` or `21/01/2021`
- Auth page layout: two-column (navy brand panel left, white form right)
- Border radius: `rounded` (minimal/sharp)
- Occupancy badges: green = owner-occupied, blue = tenant-occupied, gray = vacant
- Payment status: green = paid, amber = partial, red = overdue

---

## 17. Development Roadmap & Checklist

### Phase 1 — MVP (April 2026) — BWP 15,000

#### Foundation
- [ ] Laravel 13 project setup with Supabase PostgreSQL
- [ ] Vue 3 + Vite + Tailwind 4 frontend
- [ ] PEST test suite setup
- [ ] Multi-tenancy architecture and middleware
- [ ] Multi-tenancy isolation tests
- [ ] Laravel Passport OAuth2 authentication
- [ ] Spatie Permissions — role and permission seeding
- [ ] Default charge types seeding (24 types)
- [ ] Base layout components (sidebar, topbar, navigation)
- [ ] User management (invite, create, assign roles)
- [ ] Company settings (name, logo, colors)
- [ ] Resend integration and base email templates
- [ ] Demo seed dataset

#### Estates & Units
- [ ] Estate CRUD (create, list, view, edit)
- [ ] Estate type auto-configures charge types
- [ ] Unit CRUD per estate
- [ ] Owner CRUD linked to units
- [ ] Tenant CRUD linked to units (shown when occupancy_type = tenant_occupied)
- [ ] Occupancy type switching with tenant archiving
- [ ] Bulk unit upload via Excel with column mapping
- [ ] Downloadable Excel template
- [ ] Unit detail view (owner card + tenant card + charge config + payment history)

#### Charge Type System
- [ ] ChargeType CRUD (settings screen)
- [ ] Locked defaults (Levy, Rent) cannot be deleted
- [ ] Common presets management (rename, hide, delete)
- [ ] Custom charge type creation
- [ ] EstateChargeType junction (per-estate configuration)
- [ ] UnitChargeConfig (per-unit recurring charge amounts)

#### Billing & Invoicing
- [ ] "Run Billing" with automatic invoice generation based on billing engine logic
- [ ] Billing preview screen before dispatch
- [ ] "Create Ad-Hoc Billing" for once-off charge types
- [ ] Branded PDF invoice generation (charge type labelled)
- [ ] Bulk email dispatch via Resend
- [ ] Invoice list with charge type/status/period filters
- [ ] Duplicate invoice prevention

#### Manual Cashbook
- [ ] Manual cashbook entry (date, description, amount, credit/debit)
- [ ] Cashbook ledger view with filters
- [ ] Payment allocation to unit/invoice (auto-tag charge type)
- [ ] Advance payment handling (manual charge type selection)
- [ ] Unallocated payments view

#### Age Analysis
- [ ] Per-estate age analysis computation
- [ ] Filter by charge type
- [ ] Grouped by person (owners / tenants)
- [ ] Ageing buckets: Current / 30 / 60 / 90 / 120+
- [ ] PDF export
- [ ] Excel export

#### Dashboard
- [ ] Portfolio dashboard with arrears KPIs per charge type
- [ ] Estate dashboard with occupancy breakdown and collection stats
- [ ] Quick actions

### Phase 2 — Debt Management, Portals, Full Financials

#### Debt Management (HIGH PRIORITY)
- [ ] Collection stage workflow (reminder → demand 1 → demand 2 → attorney → arrangement → write-off)
- [ ] Letter of demand templates and PDF generation
- [ ] Automated admin fee and interest billing (using Late Payment Interest/Penalty charge types)
- [ ] Payment arrangement recording and tracking
- [ ] Bulk arrears communications via Resend
- [ ] Debt dashboard (portfolio-wide and per estate)
- [ ] Hand-over to attorney tracking

#### Financial Accounting
- [ ] Double-entry accounting engine (Admin Fund + Reserve Fund)
- [ ] Chart of accounts (pre-built SA community scheme)
- [ ] Supplier invoice capture and approval workflow
- [ ] Trial balance, Income & Expenditure, Budget vs. Actual reports

#### Bank Reconciliation
- [ ] CSV import (FNB, ABSA, Standard Bank, Nedbank, Capitec)
- [ ] Auto-match and manual matching
- [ ] Reconciliation report

#### Trustee Portal
- [ ] Login, financial dashboard, payment approval, task view, documents

#### Owner Portal
- [ ] Login, account balance, statement download, maintenance requests

#### Compliance Planner
- [ ] Calendar view, status tracking, reminders, portfolio KPI

#### Task Management
- [ ] Task CRUD, assignment, status workflow, per-unit history

#### Communications
- [ ] Email templates, bulk send, email archive, communication log

#### Document Management
- [ ] Upload to S3, categories, role-based access, version management

#### SaaS Onboarding
- [ ] Super Admin panel, tenant creation, plan management, branding

### Phase 3 — Advanced Features

- [ ] Meeting management (AGM/SGM with PQ-weighted voting)
- [ ] Transfers & clearance certificates
- [ ] Warnings & penalties
- [ ] Mobile-optimized trustee app (PWA)
- [ ] Maintenance / inspection module
- [ ] Contractor portal
- [ ] AI-assisted debt collection recommendations
- [ ] Debit order integration (Netcash or similar)
- [ ] Payment gateway for online payments
- [ ] SMS gateway integration
- [ ] Advanced analytics and trend reporting
- [ ] CSOS direct submission integration

---

## 18. UX Design Principles

1. **Professional and premium** — every screen must feel polished and trustworthy. Bold Mark's clients are property owners and trustees who expect professionalism.
2. **Progressive disclosure** — show only what's relevant. Tenant fields appear only when `occupancy_type = tenant_occupied`. Charge types show only when enabled for the estate. The admin never sees fields that don't apply.
3. **Smart defaults** — estate type auto-configures charge types. Billing engine auto-determines all invoices. The admin confirms, not configures.
4. **One-click operations** — Run Billing, Download Report, Export Cashbook. Single action with confirmation.
5. **Visual clarity** — colour-coded badges for occupancy, payment status, charge type. Traffic-light indicators (green/amber/red).
6. **Consistent patterns** — every list view follows the same layout: filterable table, status badges, search, bulk actions.
7. **Forgiving workflow** — tenant archiving reversible, cashbook allocation undoable, billing previewed before dispatch.
8. **Information density with clarity** — financial data is complex; present it clearly without overwhelming. Cards, clean tables, clear hierarchy.
9. **Mobile-responsive** — trustee portal mobile-friendly, owner portal mobile-first, management portal works on tablets.
10. **Action-oriented** — surface overdue items, pending approvals, and critical alerts prominently.
11. **Charge type management in Settings** — not cluttering daily workflow. Referenced via dropdowns and filters.

---

## 19. MCP & AI Agent Integration

### Overview

This project uses MCP (Model Context Protocol) to give AI agents deep operational control over the development environment.

### Active MCP Servers

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

### AI Agent Instructions

When building this system, AI agents must:

1. **Always read this file in full** before starting any task
2. **Use context7** to look up current documentation for any library (Laravel 13, Vue 3, Tailwind 4, Supabase, etc.)
3. **Understand the three core principles** (Section 5) before writing any business logic
4. **Reference the data model** (Section 6) for all database work — especially the ChargeType system and dual-role unit model
5. **Follow the billing engine logic** (Section 10) exactly when implementing billing
6. **Follow the brand guidelines** (Section 16) when building UI components
7. **Design for multi-tenancy from day one** — every model must include `tenant_id` scoping
8. **Use PEST for all tests** — never PHPUnit directly
9. **Never hardcode charge types** — always reference `charge_type_id` from the ChargeType table
10. **Never hardcode tenant-specific data** — all data parameterized by tenant
11. **Always check the roadmap** (Section 17) to understand what phase a feature belongs to
12. **Use the component library** (Section 16) — never rebuild buttons, inputs, modals inline

### Environment Variables Required

| Service | .env Variable | Status |
|---|---|---|
| Supabase URL | `SUPABASE_URL` | Pending |
| Supabase Anon Key | `SUPABASE_ANON_KEY` | Pending |
| Supabase Service Key | `SUPABASE_SERVICE_ROLE_KEY` | Pending |
| AWS Access Key ID | `AWS_ACCESS_KEY_ID` | Pending |
| AWS Secret Access Key | `AWS_SECRET_ACCESS_KEY` | Pending |
| AWS S3 Bucket | `AWS_BUCKET` | Pending |
| AWS Region | `AWS_DEFAULT_REGION` | `af-south-1` or `eu-west-1` |
| Pusher App ID | `PUSHER_APP_ID` | Pending |
| Pusher App Key | `PUSHER_APP_KEY` | Pending |
| Pusher App Secret | `PUSHER_APP_SECRET` | Pending |
| Pusher Cluster | `PUSHER_APP_CLUSTER` | `eu` |
| Resend API Key | `RESEND_API_KEY` | MCP active |
| GitHub Token | `GITHUB_TOKEN` | MCP active |
| Vercel Token | `VERCEL_TOKEN` | MCP active |

---

## 20. Environment & Infrastructure

### Local Development

- **OS:** macOS (Darwin 25.0.0)
- **Shell:** zsh
- **IDE:** Cursor (with Claude MCP integration) + Claude Code
- **Node.js:** Latest LTS
- **PHP:** 8.3+
- **Package Manager:** Bun (frontend), Composer (backend)

### Production URLs

| Layer | URL | Status |
|---|---|---|
| Frontend (Vue SPA) | https://portal.boldmarkprop.co.za | Live — deployed on Vercel |
| Backend (Laravel API) | TBD — subdomain of boldmarkprop.co.za (e.g. `api.boldmarkprop.co.za`) | Not yet deployed |
| Email sender domain | noreply@boldmarkprop.co.za | Verified in Resend |

> **Note for AI agents:** The frontend lives at `portal.boldmarkprop.co.za`. The API has not been deployed yet — when it is, update `APP_URL` in `.env` and create the production Resend webhook (see below).

### Deployment

- **Frontend (Vue):** Vercel — auto-deploy on merge to `main`
- **Backend (Laravel):** TBD — Laravel Forge on VPS, Vercel Serverless (PHP), or Railway.app
- **Database:** Supabase (managed PostgreSQL)
- **File Storage:** AWS S3
- **Real-time:** Pusher
- **Email:** Resend (`boldmarkprop.co.za` domain verified, sending enabled)

### Resend Email Tracking — Webhook Setup

The invoice email tracking system (Sent → Delivered → Opened) relies on Resend webhooks posting to the Laravel API at `POST /api/v1/webhooks/resend`. This endpoint is public (no auth required).

#### Current development setup (local)

| Item | Value |
|---|---|
| Dev webhook endpoint | `https://ce74-168-167-28-3.ngrok-free.app/api/v1/webhooks/resend` |
| Resend webhook ID | `68359bc3-08c9-4028-b5cf-272a896057aa` |
| Events subscribed | `email.delivered`, `email.opened` |
| Signing secret | Stored in `.env` as `RESEND_WEBHOOK_SECRET` |
| Tunnel tool | ngrok — run `ngrok http 8000` to restore the tunnel if it drops |

> **Warning:** The ngrok URL above is temporary and changes every time ngrok restarts. When the URL changes, the dev webhook in Resend must be deleted and recreated. Use `ngrok http 8000`, get the new HTTPS URL, then run:
> ```bash
> curl -s -X POST "https://api.resend.com/webhooks" \
>   -H "Authorization: Bearer $RESEND_API_KEY" \
>   -H "Content-Type: application/json" \
>   -d '{"endpoint":"https://NEW_NGROK_URL/api/v1/webhooks/resend","events":["email.delivered","email.opened"]}'
> ```
> Then delete the old webhook from the [Resend dashboard](https://resend.com/webhooks) or via API.

#### Production to-do (when API is deployed)

When the Laravel API is deployed to its production domain, do the following **once**:

1. **Delete the dev/ngrok webhook** in Resend dashboard → Webhooks → delete `68359bc3-08c9-4028-b5cf-272a896057aa`

2. **Create the production webhook:**
   ```bash
   curl -s -X POST "https://api.resend.com/webhooks" \
     -H "Authorization: Bearer $RESEND_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"endpoint":"https://YOUR_API_DOMAIN/api/v1/webhooks/resend","events":["email.delivered","email.opened"]}'
   ```

3. **Update `.env`** on the production server:
   - `APP_URL=https://YOUR_API_DOMAIN`
   - `RESEND_WEBHOOK_SECRET=<signing_secret_from_step_2_response>`

4. **Enable open tracking** in the Resend dashboard → Domains → `boldmarkprop.co.za` → Open Tracking → ON. This injects a tracking pixel into outgoing HTML emails so `email.opened` events fire.

#### How the tracking works (for AI agents)

- When an invoice email is sent via `InvoiceService::resendInvoice()`, Resend returns a unique `email_id`. This is stored on the `invoice_email_events` row with `event_type = 'sent'`.
- Resend posts `email.delivered` and `email.opened` events to the webhook endpoint.
- `ResendWebhookController::handle()` looks up the `resend_email_id` on the sent event, then creates a new `invoice_email_events` row for each tracking stage.
- The `InvoiceDetailPage.vue` reads `invoice.email_events[]` from the API and renders the three-stage timeline (Sent / Delivered / Opened) with timestamps.

### Git Branching

- `main` — production (always deployable)
- `develop` — integration
- `feature/[feature-name]` — feature branches
- All merges via Pull Request

---

## 21. Project Context & Constraints

### Engagement Model

- **Service Provider:** Optimum Quality (Pty) Ltd (Julian Tabona)
- **Client:** Bold Mark Properties (Pty) Ltd
- **Client Lead:** Justin
- **Approach:** Phased delivery, each phase scoped, quoted, and approved independently

### Commercial Terms

- **Phase 1 Budget:** BWP 15,000 (agreed 2 April 2026)
- **Phase 1 Target:** End of April 2026
- **Payment:** Prepaid before commencement of each phase
- **Ongoing:** 12-month engagement (April 2026 – March 2027), per-phase approval by Justin

### Key Risks

- **Scope creep** — this is a complex system. Define scope clearly for each phase. Track additions and quote separately.
- **WeConnectU feature parity** — the full system is 12+ months of work. Phase delivery prevents over-promising.
- **Regulatory differences** — SA regulations (STSMA, CSOS, POPIA) differ from Botswana. Phase 1 targets Botswana; SA compliance features come later.

### South African Regulatory Context (for later phases)

- **STSMA** — governs body corporate accounting, reserve funds, AGMs, trustees
- **CSOS Act** — CSOS levy obligations, dispute resolution, annual returns
- **POPIA** — data privacy for owner/tenant personal information
- **PPRA** — managing agents must be registered
- **NAMA** — industry body

---

## 22. Glossary

| Term | Definition |
|---|---|
| **Estate** | A property complex, scheme, or portfolio managed by Bold Mark. Contains multiple units. |
| **Unit** | An individual property within an estate. Always has an owner, optionally has a tenant. |
| **Owner** | Registered title holder of a unit. Always present. Liable for levies and owner-billed charges. |
| **Tenant** | Person renting a unit. Present only when `occupancy_type = tenant_occupied`. Liable for rent and tenant-billed charges. |
| **Occupancy Type** | Whether a unit is `owner_occupied`, `tenant_occupied`, or `vacant`. Drives all billing logic. |
| **Charge Type** | A configurable billing category (levy, rent, special levy, water recovery, parking, etc.). Stored in a lookup table, not hardcoded. Admin can create custom types. |
| **Levy** | Monthly fee paid by owners in a sectional title scheme to fund shared expenses (security, maintenance, insurance, reserves). |
| **Rent** | Monthly payment by a tenant for occupying the unit. |
| **Special Levy** | A once-off charge approved by the body corporate for a specific expense (e.g., roof repair). |
| **Body Corporate** | Legal entity formed by all owners in a sectional title scheme. Manages common property and collects levies. Bold Mark acts as managing agent. |
| **Sectional Title Scheme** | A property divided into individually owned units plus shared common areas, governed under one legal structure (body corporate). |
| **Billing Run** | Generating invoices for all active units in an estate. Auto-determines which charge types apply to which units. |
| **Ad-Hoc Billing** | A manual billing action for once-off charge types, applied to selected units. |
| **Cashbook** | A manual ledger of bank statement transactions, allocated to units and invoices by charge type. |
| **Age Analysis** | Arrears report showing outstanding balances in ageing buckets (Current / 30 / 60 / 90 / 120+ days), filterable by charge type. |
| **PQ (Participation Quota)** | A fraction assigned to each unit in a sectional title scheme, used for levy apportionment and voting weight. |
| **WeConnectU** | The incumbent SA property management platform that BoldMark PMS replaces. |
| **Tenant (multi-tenancy)** | In the SaaS context, a managing agent company (e.g., Bold Mark Properties) that uses the platform. Not to be confused with a unit tenant. |

---

## 23. Key Contacts & Resources

### People

| Person | Role | Contact |
|---|---|---|
| Julian Tabona | Developer (Optimum Quality) | Service provider — project lead |
| Justin | Client Lead (Bold Mark Properties) | Primary decision maker |
| Bold Mark Properties Team | Client | www.boldmarkprop.co.za |

### Reference Systems

| System | URL | Notes |
|---|---|---|
| BoldMark PMS (our system) | https://portal.boldmarkprop.co.za | Phase 0 sign-in page live |
| WeConnectU | https://app.weconnectu.co.za | Current system Bold Mark uses |
| Bold Mark Website | https://www.boldmarkprop.co.za | Client's public website |

### Documentation

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
| Pusher Docs | https://pusher.com/docs |
| PEST PHP Docs | https://pestphp.com/docs |

---

## Changelog

| Date | Change | Author |
|---|---|---|
| 2026-03-30 | Initial CLAUDE.md created | Julian Tabona / Claude Code |
| 2026-04-02 | Complete rewrite: dual-role unit model, flexible charge type system (24 default types), billing engine logic, updated data model, Phase 1 MVP workflow, UX principles, updated tech stack | Julian Tabona / Claude (claude.ai) |
| 2026-04-03 | UI Screens fully specified from Lovable prototype. All Phase 1 screens: Dashboard, Estates, Estate Detail (units table, charts, bulk import), Unit Detail (Create Invoice, change log/emails, send message), Tenant Detail, Billing & Invoicing (Run Billing, Ad-Hoc Billing, Export, billing schedule, invoice vs statement), Invoice Detail (branded preview, payment history, email tracking), Cashbook (summary cards, auto-allocate, charts), Cashbook Entry Detail (proof of payment, change log, edit mode), Age Analysis (with example), Users. Billing Engine: statement generation, automatic scheduling, data model. Global: AppTableToolbar (date range/filter/sort/views), change log pattern, skeleton loading, shared modals. Real-world examples throughout. | Julian Tabona / Claude (claude.ai) |
| 2026-04-03 | Age Analysis page fully specified from Lovable prototype screenshots. Added: summary cards (Current/30/60/90/120+/Total), Owner and Tenant tab split with separate tables, clickable names linking to Owner/Tenant detail pages, 4 charts (Arrears by Ageing Bucket, Owners Outstanding, Tenants Outstanding, Owner vs Tenant Split donut), Owner Detail page spec (Section 8.14.1). Standardised Export modal as reusable `AppExportModal` component (CSV/Excel/PDF format selection + record count) — single Export button pattern across all tables. | Julian Tabona / Claude (claude.ai) |
| 2026-04-03 | Users page fully specified: expanded role list (Admin, Portfolio Manager, Financial Controller, Portfolio Assistant, Trustee/Director, Owner, Tenant, Contractor), Internal/External tab split, user category in invite modal, example data for all 10 users. New Settings page (Section 8.16): Account tab (profile, change password, security with 2FA and active sessions), Company tab (company name/slogan controlling sidebar branding, contact details, country/currency, branding colours controlling dashboard theme, charge types management with full CRUD). Charge type management moved from standalone section 8.6 to Settings → Company tab. Sidebar updated to reflect branding is dynamic from company settings. | Julian Tabona / Claude (claude.ai) |
| 2026-04-03 | Cashbook data model refined: removed `allocated` boolean — allocation status now derived from `invoice_id` presence. Added `parent_entry_id` and `notes` fields. New Section 6.8.1 (Partial Allocation & Payment Splitting): when payment exceeds invoice, system splits into allocated + unallocated remainder entries. Documented advance payment and overpayment workflows. New Section 6.8.2 (Credit Balances): unallocated credits surface in unit detail, age analysis (net of credits), and statements. Billing engine rule 5 updated: `applies_to = either` resolves to current occupant (tenant if tenant-occupied, owner otherwise including vacant). Allocate workflow updated with split preview, partial payment, and exact match flows. Business rules 10-12 updated. | Julian Tabona / Claude (claude.ai) |
| 2026-04-05 | Added Business Rule 18: Sectional Title estates do not support tenant occupancy. Units in `sectional_title` estates are restricted to `owner_occupied` or `vacant` only — `tenant_occupied` is never valid. Frontend: Edit Unit modal hides Tenant Details section and removes "Tenant" from occupancy dropdown for sectional title estates. Backend: `CreateUnitRequest` and `UpdateUnitRequest` now reject `tenant_occupied` and any `tenant` payload via `withValidator`. Edit Unit modal spec in Section 8.7 updated to document estate-type-dependent occupancy options. | Julian Tabona / Claude Code |
| 2026-04-07 | Renamed "Change Log" → "Activity" platform-wide. Model `UnitChangeLog` → `UnitActivity`, table `unit_change_logs` → `unit_activities`, API route `/change-logs` → `/activities`. All service methods, controller actions, frontend refs, computed properties, and UI labels updated. Concept expanded to support any loggable event (invoice sent, email dispatched, etc.), not just field-level diffs. | Julian Tabona / Claude Code |
| 2026-04-07 | Simplified database seeding: `DatabaseSeeder` now automatically calls `DemoSeeder` when `APP_ENV` is not `production`. Single command to reset and reseed: `php artisan migrate:fresh --seed`. Seeding strategy section in CLAUDE.md updated with exact credentials, seeder structure, and reset command. | Julian Tabona / Claude Code |

---

> This document is a living specification. Update it as requirements are clarified,
> features are completed, and new information is discovered.
> **Never let this file become stale — it is the memory of this project.**
