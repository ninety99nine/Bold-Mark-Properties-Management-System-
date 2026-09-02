# BoldMark PMS — CI/CD & Deployment Status
Last updated: 4 May 2026

---

## Current State: BLOCKED — Awaiting AWS Account Unblock

The CI/CD pipeline is fully built and green. Deployment cannot proceed because the AWS account
is blocked by a security incident. A support case has been filed and replied to. We are waiting
for AWS to restore full access before any infrastructure work can continue.

---

## Repository

- **Repo:** boldmark-boo (GitHub — Julian Tabona)
- **Active branch:** `phase-2` (pushed, CI green)
- **Target deploy branch:** `main` (merging phase-2 → main triggers full CI/CD deploy)

---

## What Is Done

### Code & Tests
- [x] Phase 2 code complete — 288 files committed to `phase-2` branch
- [x] All 759 Pest tests passing (fixed by a separate agent: InvoiceFactory `charge_type_id`,
      UnitFactory `afterCreating` owner creation)
- [x] GitHub Actions CI pipeline passing in 42s (test job only, on non-main branches)

### CI/CD Pipeline (`.github/workflows/ci-cd.yml`)
- [x] PHP 8.4 configured (lock file requires `^8.4` — spatie/laravel-permission 7.2.4)
- [x] Laravel Passport keys generated in CI (`php artisan passport:keys --force`)
- [x] 3-job pipeline wired: `test → build-images → deploy`
  - `test`: runs on every push/PR
  - `build-images`: runs on push to `main` only — builds PHP-FPM + Nginx Docker images, pushes to ECR
  - `deploy`: runs on push to `main` only — SCPs files to EC2, SSHes in, runs `deploy.sh`

### Docker
- [x] `Dockerfile` — multi-stage: Node (Vue build) → Composer (PHP deps) → PHP 8.4-FPM Alpine
- [x] `Dockerfile.nginx` — Nginx + Vue static files
- [x] `docker-compose.yml` — full production stack: app, nginx, db (MySQL), redis, horizon
- [x] `docker/scripts/entrypoint.sh` — runs migrations, seeds, cache warm on container start
- [x] `docker/scripts/deploy.sh` — zero-ish-downtime deploy (pull → swap → prune)
- [x] `docker/scripts/setup-ec2.sh` — first-time EC2 server setup (Docker, AWS CLI, etc.)
- [x] `docker/scripts/first-deploy.sh` — initial migrations + demo seed after first deploy
- [x] `docker/scripts/healthcheck.sh` — PHP-FPM health probe
- [x] `docker/scripts/backup-db.sh` — database backup script

### Infrastructure Scripts
- [x] `scripts/aws-bootstrap.sh` — creates IAM users, ECR repos, S3 bucket, EC2 keypair.
      Supports `--rotate-key` flag to rotate the deployer IAM access key.

### Configuration
- [x] `api/.env.production.example` — production environment template (all variables documented,
      CHANGE_ME placeholders for secrets). Tracked in git (exception added to `.gitignore`).
- [x] `GITHUB-SECRETS-CHECKLIST.md` — lists all 8 required GitHub secrets with sources

### AWS (Pre-Block — Partial)
- [x] AWS Activate credits active: $4,415.40 remaining ($5,000 from University of Toronto)
- [x] IAM user `boldmark-deployer` exists (access key needs rotation via `--rotate-key`)
- [x] IAM user `boldmark-bootstrap` exists
- [ ] EC2 instance NOT created (blocked by security incident)
- [ ] Elastic IP NOT allocated
- [ ] ECR repositories may or may not exist (bootstrap script handles this idempotently)
- [ ] S3 bucket `boldmark-uploads-{ACCOUNT_ID}` may or may not exist

---

## What Is Pending (and Why)

### BLOCKED: AWS Account Security Incident
- AWS flagged the account as potentially compromised and blocked EC2 launch (and likely all
  resource creation via both console and API)
- Error when launching EC2: "This account is currently blocked and not recognized as a valid account"
- User reset root password and confirmed MFA is set up (4 devices including Authy — pre-existing)
- User replied to AWS support case on 4 May 2026 ~16:40 with security confirmation message
- **Status: Waiting for AWS support to restore full access**
- Nothing can be done on AWS infrastructure until this is resolved

### Not Yet Done (in order)
- [ ] AWS account unblocked
- [ ] Rotate deployer IAM key (old key may be compromised — required before first deploy)
- [ ] Run `bash scripts/aws-bootstrap.sh --rotate-key` to get fresh credentials + create EC2
- [ ] Configure 8 GitHub secrets (see `GITHUB-SECRETS-CHECKLIST.md`)
- [ ] SSH into new EC2 and run `docker/scripts/setup-ec2.sh`
- [ ] Merge `phase-2` → `main` (triggers full CI/CD: tests → build Docker images → deploy)
- [ ] Run `docker/scripts/first-deploy.sh` (initial DB migrations + demo seed)
- [ ] Point DNS: `portal.boldmarkprop.co.za` → Elastic IP

---

## Exact Next Steps (in order)

### Step 1 — Wait for AWS to unblock the account
Check the AWS Support Center for a response to the open case.
You cannot proceed until this is done.

### Step 2 — Rotate the deployer IAM access key
The old `boldmark-deployer` key may be compromised (reason for the security flag).
Run this with root/admin credentials exported:

```bash
export AWS_ACCESS_KEY_ID=<root-or-admin-key>
export AWS_SECRET_ACCESS_KEY=<root-or-admin-secret>
bash scripts/aws-bootstrap.sh --rotate-key
```

This will:
- Delete the existing `boldmark-deployer` access key
- Create a new one
- Print the new `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`
- Create the EC2 instance (t3.small, eu-west-2), Elastic IP, ECR repos, S3 bucket if not existing

Save the output — you need the new credentials for Step 3.

### Step 3 — Configure GitHub Secrets
Go to GitHub repo → Settings → Secrets and variables → Actions → New repository secret.
Add all 8 secrets (see `GITHUB-SECRETS-CHECKLIST.md` for full details):

| Secret | Value |
|---|---|
| `AWS_ACCESS_KEY_ID` | New key from Step 2 |
| `AWS_SECRET_ACCESS_KEY` | New secret from Step 2 |
| `EC2_HOST` | Elastic IP allocated in Step 2 |
| `EC2_USER` | `ubuntu` |
| `EC2_SSH_KEY` | Contents of the `.pem` private key file (created by bootstrap) |
| `EC2_SSH_PORT` | `22` |
| `PRODUCTION_ENV` | Full contents of `api/.env.production.example` with all CHANGE_ME values filled in |
| `VITE_PUSHER_APP_KEY` | From Pusher dashboard |
| `VITE_PUSHER_APP_CLUSTER` | `eu` |

For `PRODUCTION_ENV`: copy `api/.env.production.example`, fill in every `CHANGE_ME` value:
- `APP_KEY`: run `php artisan key:generate --show` locally
- `DB_PASSWORD`: choose a strong password
- `DB_ROOT_PASSWORD`: choose a strong password
- `REDIS_PASSWORD`: choose a strong password
- `PUSHER_*`: from pusher.com → your app → App Keys
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`: same new key from Step 2
- `AWS_BUCKET`: `boldmark-uploads-{your-account-id}` (bootstrap prints this)
- `RESEND_API_KEY`: from resend.com → API Keys
- `RESEND_WEBHOOK_SECRET`: from resend.com → Webhooks

### Step 4 — First-time EC2 server setup
SSH into the new EC2 and run the setup script:

```bash
ssh -i path/to/boldmark-keypair.pem ubuntu@<ELASTIC_IP>
bash /tmp/setup-ec2.sh   # or copy it up first via scp
```

Or copy and run directly:

```bash
scp -i path/to/boldmark-keypair.pem docker/scripts/setup-ec2.sh ubuntu@<ELASTIC_IP>:/tmp/
ssh -i path/to/boldmark-keypair.pem ubuntu@<ELASTIC_IP> 'bash /tmp/setup-ec2.sh'
```

This installs: Docker, Docker Compose, AWS CLI, configures log rotation, firewall rules.

### Step 5 — Trigger full deploy (merge phase-2 → main)
On GitHub, open a PR from `phase-2` → `main` and merge it.
This triggers the full 3-job CI/CD pipeline:
1. Tests (Pest + Vite build) — ~42s
2. Build Docker images → push to ECR (~5–10 min)
3. Deploy to EC2 (pull images, restart containers, run migrations) (~3–5 min)

### Step 6 — First-deploy initialisation
After the first deploy completes, SSH in and run:

```bash
ssh -i path/to/boldmark-keypair.pem ubuntu@<ELASTIC_IP>
cd /opt/boldmark
bash docker/scripts/first-deploy.sh
```

This runs initial database migrations and seeds demo data.

### Step 7 — Point DNS
In your DNS provider, add an A record:
- **Host:** `portal`
- **Value:** `<ELASTIC_IP>`
- **TTL:** 300

The Nginx container is configured for `portal.boldmarkprop.co.za` and will serve the Vue SPA
+ Laravel API once DNS propagates.

---

## Architecture Reference

```
GitHub Actions (CI/CD)
    │
    ├── test job (every push)
    │     └── PHP 8.4 + Pest + Vite build
    │
    └── on merge to main:
          ├── build-images → push to ECR (boldmark-app, boldmark-nginx)
          └── deploy → SSH to EC2 → docker compose up

EC2 (eu-west-2, t3.small) with Docker Compose:
    ├── nginx (Nginx + Vue SPA static files)
    ├── app (PHP-FPM 8.4 + Laravel)
    ├── db (MySQL 8)
    ├── redis (Redis 7)
    └── horizon (Laravel Horizon — queue worker)

External services:
    ├── ECR — Docker image registry
    ├── S3 — file uploads (boldmark-uploads-*)
    ├── Pusher — real-time events
    └── Resend — transactional email
```

---

## Key Files

| File | Purpose |
|---|---|
| `.github/workflows/ci-cd.yml` | Full CI/CD pipeline definition |
| `Dockerfile` | PHP-FPM app image (multi-stage) |
| `Dockerfile.nginx` | Nginx + Vue static files image |
| `docker-compose.yml` | Production Docker Compose stack |
| `docker/scripts/deploy.sh` | Called by CI on every deploy to main |
| `docker/scripts/setup-ec2.sh` | One-time EC2 server setup |
| `docker/scripts/first-deploy.sh` | One-time initial migrations + seed |
| `scripts/aws-bootstrap.sh` | AWS resource creation + IAM key rotation |
| `api/.env.production.example` | Production .env template |
| `GITHUB-SECRETS-CHECKLIST.md` | GitHub secrets reference |
