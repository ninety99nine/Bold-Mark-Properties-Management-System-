# BoldMark PMS — Deployment Runbook

> **Audience:** Developers and operators deploying or maintaining the production stack.
> **Architecture:** Single AWS EC2 instance (Docker Compose) in `eu-west-2` (London).
> See [`docs/PROJECT-SPEC.md`](docs/PROJECT-SPEC.md) Sections 15 & 20 for the full picture.

---

## Quick reference

| Task | Command / Location |
|---|---|
| Trigger production deploy | `git push origin main` |
| View live deploy logs | GitHub → Actions → latest run |
| SSH into prod | `ssh -i scripts/boldmark-ec2.pem ubuntu@<elastic-ip>` |
| View running containers | `cd /opt/boldmark && docker compose ps` |
| App logs | `docker compose logs -f app` |
| Tail Nginx access log | `docker compose exec nginx tail -f /var/log/nginx/access.log` |
| Run a one-off artisan command | `docker compose exec app php artisan <cmd>` |
| Rollback | See [Rollback](#rollback) below |
| Restore DB backup | See [DB restore](#database-restore) below |

---

## First-time setup (run ONCE)

You only do this once when standing up production from scratch.

### 1. Provision AWS

You need an AWS account and either root access keys or an admin IAM user.

```bash
# Set credentials (only needed for this script — they are NOT stored anywhere)
export AWS_ACCESS_KEY_ID=AKIA...
export AWS_SECRET_ACCESS_KEY=...

# Run the bootstrap (uses AWS CLI in Docker, no local install required)
bash scripts/aws-bootstrap.sh
```

This script provisions:
- IAM user `boldmark-deployer` with ECR + S3 permissions only (least privilege)
- ECR repositories: `boldmark-app`, `boldmark-nginx`
- S3 buckets: `boldmark-uploads-<account_id>`, `boldmark-db-backups-<account_id>` (private, encrypted, lifecycle on backups)
- EC2 key pair `boldmark-deploy` (private key written to `scripts/boldmark-ec2.pem`)
- Security group `boldmark-sg` (open: 22, 80, 443)
- EC2 instance: `t3.small`, Ubuntu 24.04 LTS, 30 GB gp3 EBS, default VPC
- Elastic IP, attached to the instance

At the end it writes `scripts/.aws-bootstrap-output.txt` (gitignored) with every value you need
for GitHub secrets and the production `.env`.

### 2. Configure GitHub secrets

Open: `https://github.com/<owner>/<repo>/settings/secrets/actions`

Paste these (all values are in `scripts/.aws-bootstrap-output.txt`):

| Secret | Value |
|---|---|
| `AWS_ACCESS_KEY_ID` | from output |
| `AWS_SECRET_ACCESS_KEY` | from output |
| `EC2_HOST` | Elastic IP from output |
| `EC2_USER` | `ubuntu` |
| `EC2_SSH_KEY` | full contents of `scripts/boldmark-ec2.pem` (`cat scripts/boldmark-ec2.pem`) |
| `EC2_SSH_PORT` | `22` |
| `PRODUCTION_ENV` | full `.env` file contents (template printed in output — fill in real values) |
| `VITE_PUSHER_APP_KEY` | from Pusher dashboard |
| `VITE_PUSHER_APP_CLUSTER` | `eu` |

### 3. Bootstrap the EC2 instance

```bash
# Copy the EC2 setup script
scp -i scripts/boldmark-ec2.pem docker/scripts/setup-ec2.sh ubuntu@<elastic-ip>:/tmp/

# SSH in and run it
ssh -i scripts/boldmark-ec2.pem ubuntu@<elastic-ip>
sudo bash /tmp/setup-ec2.sh
```

This installs Docker, AWS CLI, certbot, sets up `/opt/boldmark`, and schedules nightly DB backups.

### 4. Point DNS at the Elastic IP

In your DNS provider (Cloudflare/Route53/etc.), create:

```
Type:  A
Name:  portal
Value: <elastic-ip>
TTL:   300
```

Wait for propagation:
```bash
dig +short portal.boldmarkprop.co.za
# should return the Elastic IP within ~5 min
```

### 5. Issue the SSL certificate (one-time)

DNS must resolve before this works. Then on the EC2 instance:

```bash
sudo docker run --rm \
  -p 80:80 \
  -v /etc/letsencrypt:/etc/letsencrypt \
  certbot/certbot certonly --standalone \
  -d portal.boldmarkprop.co.za \
  --email noreply@boldmarkprop.co.za \
  --agree-tos --no-eff-email --non-interactive
```

After this, the `certbot` container in `docker-compose.yml` auto-renews every 12 hours.

### 6. First deploy

```bash
# From your local machine
git push origin main
```

Watch the deploy in **GitHub → Actions**. The pipeline:
1. Runs Pest tests
2. Builds & pushes both Docker images to ECR
3. SCPs compose + nginx + scripts to EC2
4. SSHes in, writes `.env`, pulls images, runs `deploy.sh`

### 7. First-deploy seed (one-time, after step 6 succeeds)

```bash
ssh -i scripts/boldmark-ec2.pem ubuntu@<elastic-ip>
sudo bash /opt/boldmark/docker/scripts/first-deploy.sh
```

This seeds roles & permissions, creates the super admin, installs Passport keys, warms caches.

### 8. Verify

- https://portal.boldmarkprop.co.za → login page
- https://portal.boldmarkprop.co.za/horizon → queue dashboard
- https://portal.boldmarkprop.co.za/up → `{"status":"ok"}`

You're live.

---

## Day-to-day operations

### Standard deploy

```bash
git push origin main    # That's it. Pipeline takes ~6-8 minutes end-to-end.
```

### Manual deploy without pushing code

GitHub → Actions → CI/CD Pipeline → Run workflow → branch: main → Run

### Run a database migration without code changes

If you want to backfill data or add a one-off migration without touching code:

```bash
ssh -i scripts/boldmark-ec2.pem ubuntu@<elastic-ip>
cd /opt/boldmark
docker compose exec app php artisan migrate --force
```

### View Horizon (queue) dashboard

https://portal.boldmarkprop.co.za/horizon — login with super admin or any user with the `view-horizon` permission.

---

## Rollback

If a deploy goes bad and `/up` is failing, roll back to the previous good image:

```bash
ssh -i scripts/boldmark-ec2.pem ubuntu@<elastic-ip>
cd /opt/boldmark

# Find the previous SHA in ECR (second-most-recent)
ACCOUNT=$(aws sts get-caller-identity --query Account --output text)
ECR=$ACCOUNT.dkr.ecr.eu-west-2.amazonaws.com
PREV_SHA=$(aws ecr describe-images --repository-name boldmark-app --region eu-west-2 \
  --query 'sort_by(imageDetails,& imagePushedAt)[-2].imageTags[0]' --output text)

echo "Rolling back to: $PREV_SHA"

# Pin the image refs and re-deploy
sudo tee .deploy.env > /dev/null <<EOF
APP_IMAGE=$ECR/boldmark-app:$PREV_SHA
NGINX_IMAGE=$ECR/boldmark-nginx:$PREV_SHA
EOF

docker compose --env-file .deploy.env pull
APP_IMAGE=$ECR/boldmark-app:$PREV_SHA \
NGINX_IMAGE=$ECR/boldmark-nginx:$PREV_SHA \
  bash docker/scripts/deploy.sh
```

> **Note:** This rolls back the application code only. If migrations were applied, you may also need to restore the DB backup from before the bad deploy (next section).

---

## Database restore

Backups run nightly at 02:00 UTC and live in S3 (`boldmark-db-backups-<account>/database/`).
Latest 30 days are in `STANDARD`, 30-365 days in `STANDARD_IA`, deleted after 365 days.

```bash
ssh -i scripts/boldmark-ec2.pem ubuntu@<elastic-ip>
cd /opt/boldmark

ACCOUNT=$(aws sts get-caller-identity --query Account --output text)
BUCKET=boldmark-db-backups-$ACCOUNT

# List recent backups
aws s3 ls s3://$BUCKET/database/ --region eu-west-2 | tail -10

# Pick one and restore
BACKUP=20260504_020000.sql.gz   # change this
aws s3 cp s3://$BUCKET/database/$BACKUP /tmp/$BACKUP --region eu-west-2
gunzip /tmp/$BACKUP

# Source DB password from .env
source <(grep -E '^DB_ROOT_PASSWORD=' .env | sed 's/^/export /')

docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASSWORD" boldmark < /tmp/${BACKUP%.gz}
echo "Restored $BACKUP"
```

---

## Common issues

### "Connection refused" from /up after deploy

Wait 30 seconds. The `app` container takes ~10-30s to bootstrap.
If it's still failing, check logs:
```bash
docker compose logs --tail=100 app
docker compose logs --tail=100 nginx
```

### "SQLSTATE[HY000] [2002] Connection refused" in app logs

The `db` service hasn't finished starting. Wait or check:
```bash
docker compose ps db
docker compose logs db
```

### Out of disk space on EC2

Run image pruning manually:
```bash
docker system prune -af
docker builder prune -af
sudo journalctl --vacuum-time=7d
```

### SSL cert expired

Certbot auto-renews every 12 hours. If it stopped, manually renew:
```bash
docker compose exec certbot certbot renew --webroot -w /var/www/certbot
docker compose restart nginx
```

### Need to update an environment variable

Update the **`PRODUCTION_ENV` GitHub secret** (Repo Settings → Secrets → Actions),
then trigger a redeploy (push or manual workflow_dispatch). The deploy job rewrites
`/opt/boldmark/.env` from the secret on every deploy.

### Need to rotate the deployer AWS keys

```bash
export AWS_ACCESS_KEY_ID=<root-or-admin-key>
export AWS_SECRET_ACCESS_KEY=...

# Delete the old key, create a new one
aws iam list-access-keys --user-name boldmark-deployer
aws iam delete-access-key --user-name boldmark-deployer --access-key-id <old-key-id>
aws iam create-access-key --user-name boldmark-deployer
```

Then update the `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` GitHub secrets.

---

## Security notes

- **Never commit** `scripts/boldmark-ec2.pem`, `scripts/.aws-bootstrap-output.txt`,
  or any production `.env` file. The `.gitignore` covers these — keep it that way.
- **Rotate the deployer IAM keys** every 90 days (see above).
- **Restrict SSH access**: the security group currently allows SSH from `0.0.0.0/0` for
  bootstrap convenience. Once setup is stable, narrow it to your IP:
  ```bash
  aws ec2 revoke-security-group-ingress --group-name boldmark-sg --protocol tcp --port 22 --cidr 0.0.0.0/0
  aws ec2 authorize-security-group-ingress --group-name boldmark-sg --protocol tcp --port 22 --cidr <your-ip>/32
  ```
- **Production `.env` lives only in two places:** the `PRODUCTION_ENV` GitHub secret, and
  `/opt/boldmark/.env` on the EC2 instance (chmod 600). Never log it, never email it.
