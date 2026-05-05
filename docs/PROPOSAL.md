# Bold Mark Properties Management System
## Delivery Summary & Pricing Justification

**Prepared by:** Optimum Quality (Pty) Ltd — Julian Tabona
**Date:** April 2026
**Client:** Bold Mark Properties (Pty) Ltd

---

## What Has Been Built

A fully functional, production-ready property management platform purpose-built for Bold Mark Properties. Below is a complete breakdown of every module and feature delivered.

---

### 1. Authentication & Security
- Secure email/password login with OAuth2 token authentication (Laravel Passport)
- Password reset via email (Resend integration)
- Session management (remember me, logout)
- Role-based access control (Company Admin, Portfolio Manager, Financial Controller, Portfolio Assistant)
- Multi-tenant data isolation — each organisation's data is completely separate

---

### 2. Multi-Tenant Architecture
- Full SaaS infrastructure — Bold Mark can onboard additional companies onto the same platform
- Subdomain-based tenant resolution (e.g. `boldmark.oursystem.com`)
- Per-tenant branding (company name, logo, primary and secondary colours)
- Complete data isolation at database level — no cross-tenant data leakage

---

### 3. Dashboard & Reporting
- **Portfolio Dashboard** — total estates, total units, total outstanding arrears, occupancy rate, monthly collection vs last month
- **Debt Dashboard** — arrears overview with breakdown
- Quick action buttons: Run Billing, Upload Cashbook, View Age Analysis
- Recent invoices panel with status filters (All / Paid / Overdue / Sent / Draft)
- Estate overview panel with occupancy breakdown

---

### 4. Estate Management
- Create, view, edit estates
- Estate types: Sectional Title, Residential Rental, Commercial Rental, Mixed
- Per-estate default levy and rent amounts
- Billing day configuration per estate
- Estate-level charge type activation (which charge categories apply to each estate)
- Bulk unit import via Excel/CSV with column-mapping preview
- Downloadable import template
- Estate detail page with unit table, occupancy charts, invoice status chart, top arrears chart

---

### 5. Unit Management
- Full unit CRUD with per-estate validation rules
- Occupancy types: Owner-Occupied, Tenant-Occupied, Vacant
- Sectional Title estates enforce owner-only occupancy (business rule enforced at API and UI)
- Per-unit levy override amounts
- Per-unit tenant management (lease dates, rent amount, lease document upload)
- Unit balance tracking (outstanding invoices minus credits)
- Unit detail page: owner card, tenant card, charge configuration, invoices, payments received, tenant history, activity log, emails sent

---

### 6. Owner Management
- Owner records linked to units
- Full contact details (name, email, phone, ID number, address)
- Owner detail page: owned units, invoice history, payment history, age analysis summary, communications log

---

### 7. Tenant Management
- Tenant records with full contact and lease details
- Lease document upload and storage (PDF)
- Tenant history per unit (current and all past tenants)
- Move-in and move-out workflow
- Soft-delete (archive) — tenant history preserved permanently for audit
- Tenant detail page: contact details, lease details, lease document preview/download, invoice history, payment history

---

### 8. Charge Type System
- 24 pre-configured charge types (Levy, Rent, Special Levy, Water Recovery, Electricity Recovery, Gas, Sewerage, Refuse, Late Interest, Late Penalty, Insurance Excess, Key Deposit, Damage Deposit, Parking Rental, Storage Rental, Moving-In Fee, Moving-Out Fee, Access Card Fee, Gym Access, Pool Access, Garden Maintenance, Pet Levy, Security Contribution, Legal Recovery)
- System-locked defaults (Levy and Rent) — cannot be deleted
- Admin can create unlimited custom charge types
- Each charge type: code, name, applies to (Owner / Tenant / Either), recurring or ad-hoc
- Per-estate charge type activation
- Per-unit recurring charge configuration (e.g. Parking R150/month for Unit 12)

---

### 9. Billing & Invoicing
- **Run Monthly Billing** — one-click billing run with automatic invoice generation:
  - Levy invoices to all owners
  - Rent invoices to all active tenants
  - Per-unit recurring charges (parking, pet levy, gym, etc.) to correct recipients
  - Billing preview before dispatch — admin reviews all invoices before sending
  - Duplicate invoice prevention
- **Ad-Hoc Billing** — once-off charges (special levy, insurance excess, moving fees, etc.) for all units or selected units
- Branded PDF invoice generation with company branding
- Bulk email dispatch via Resend
- Invoice tracking: Unpaid, Partially Paid, Paid, Overdue, Sent, Draft
- Email dispatch tracking (Sent → Delivered → Opened) via Resend webhooks — evidence for debt collection
- Invoice detail page with full payment history and email tracking timeline

---

### 10. Cashbook (Payment Recording & Allocation)
- Manual cashbook entries (date, description, amount, credit/debit)
- Payment allocation to units and invoices
- Charge type auto-tagged from invoice on allocation
- **Payment splitting** — when a payment exceeds an invoice, the system automatically splits into allocated + unallocated remainder
- Advance payment handling — credits sit as unallocated against the unit until future invoices are created
- Unallocated entries queue with warning banner
- Auto-allocate feature (matches payments to invoices by description and amount)
- Cashbook summary cards: Total Credits, Total Debits, Net Balance, Unallocated count/amount
- Full cashbook entry detail page with proof of payment upload, change log, edit mode
- Allocated vs Unallocated status derived automatically from invoice linkage

---

### 11. Age Analysis
- Real-time arrears report computed from invoices and cashbook entries
- Ageing buckets: Current / 30 Days / 60 Days / 90 Days / 120+ Days
- Filter by: charge type (levy only, rent only, etc.), estate
- Owner and Tenant tabs (separate views)
- Clickable names linking to Owner/Tenant detail pages
- Summary cards for each ageing bucket plus total outstanding
- 4 charts: Arrears by Ageing Bucket, Owners Outstanding, Tenants Outstanding, Owner vs Tenant Split
- Export: CSV, Excel, PDF with record count selection

---

### 12. User Management
- Invite users by email
- User categories: Internal Staff (Admin, Portfolio Manager, Financial Controller, Portfolio Assistant) and External (Trustee/Director, Owner, Tenant, Contractor)
- Assign users to specific estates
- User status: Active, Invited, Inactive
- Role-based access: what each role can see and do is enforced at API level

---

### 13. Company Settings & Branding
- Company name and slogan (controls sidebar branding across the whole app)
- Primary and secondary colour configuration (re-themes the entire application in real-time)
- Contact details, country, currency settings
- Full charge type management (add, edit, hide, delete custom types)
- Account settings: profile, change password, security

---

### 14. Communications & Email Tracking
- Send messages to owners or tenants from unit/tenant detail pages
- Template-based emails with merge fields (recipient name, unit number, balance, dates, etc.)
- Owner-specific and tenant-specific templates (no cross-contamination)
- All emails logged per unit with delivery status (Sent / Delivered / Opened)
- Email tracking via Resend webhooks — full audit trail
- Resend integration with `boldmarkprop.co.za` sender domain verified and active

---

### 15. Activity Log (Audit Trail)
- Every change to every unit, invoice, cashbook entry, and tenant is logged
- Field-level diff: shows exactly what changed (e.g. "Email: old@email.com → new@email.com")
- Who made the change and when
- Change Details modal on click
- Platform-wide pattern — every record in the system has a complete history

---

### 16. Saved Table Views
- Users can save custom filter + sort + date range combinations as named views
- Views persist per user per screen (e.g. "Overdue Tenants", "High Balance Owners")
- View tabs above every data table for quick switching

---

## Pricing Justification

### Development Fee — BWP 15,000 (One-Time)

This fee covers the complete design, development, and delivery of the platform described above: 16 functional modules, 30 frontend screens, 19 API controllers, 17 service classes, 30 database migrations, full multi-tenant SaaS infrastructure, OAuth2 authentication, role-based permissions, PDF generation, Excel import/export, email integration, real-time email tracking, and a branded property management system from the ground up.

**Comparable market rate:** A system of this scope and quality — built to production standard with proper architecture, test coverage, and a SaaS multi-tenant foundation — would typically cost R200,000–R400,000+ from an agency or R80,000–R120,000 from a senior freelancer. The BWP 15,000 rate reflects the long-term partnership and the shared interest in the success of this platform.

---

### Monthly Retainer — BWP 3,000/month

The monthly retainer covers the ongoing costs and services required to keep the platform live, secure, and maintained:

| Item | Detail |
|---|---|
| **AWS Cloud Hosting** | EC2 + ECR + S3 infrastructure — compute, container registry, file hosting |
| **Database (MySQL 8 on EC2)** | Production-grade MySQL 8 with automated nightly backups to S3 (30-day retention) |
| **Email Infrastructure (Resend)** | Transactional email delivery — invoices, statements, arrears notices, user invitations, password resets. Delivery tracking and webhook infrastructure. |
| **SSL Certificates & Security** | HTTPS enforcement, certificate renewal, web application firewall (WAF) |
| **Domain & DNS Management** | `portal.boldmarkprop.co.za` and API subdomain DNS management |
| **Backups & Disaster Recovery** | Automated daily database backups with 30-day retention |
| **Security Monitoring** | Intrusion detection, failed login alerts, suspicious activity monitoring |
| **Platform Updates & Maintenance** | Dependency updates, security patches, bug fixes |
| **Uptime Monitoring** | 24/7 uptime monitoring with alerts |
| **Support** | Ongoing technical support for platform issues |

**Why BWP 3,000/month is fair value:**

The raw infrastructure cost for AWS (EC2 compute + ECR + S3 storage + data transfer), Resend, and monitoring tools runs approximately BWP 800–1,200/month at current usage levels. The balance covers dedicated support time, maintenance, and the technical management of the infrastructure — work that would otherwise require a full-time DevOps resource or agency retainer costing 5–10× this amount.

As Bold Mark grows and onboards more estates and units, the infrastructure scales accordingly — this retainer ensures that scaling is managed proactively, not reactively.

---

*Optimum Quality (Pty) Ltd — delivering cutting-edge property management technology to the Botswana and Southern Africa market.*
