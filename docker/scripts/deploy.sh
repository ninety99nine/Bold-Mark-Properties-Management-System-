#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
# AWS / EC2 Deploy Script — BoldMark PMS
# ═══════════════════════════════════════════════════════════════════════
# Pulls pre-built images from ECR, restarts services, runs migrations,
# issues/renews the Let's Encrypt cert, and warms caches.
# Called by .github/workflows/aws-ci-cd.yml after images are pushed to ECR.
#
# This is the AWS twin of deploy-do.sh (DigitalOcean builds on-server;
# AWS pulls pre-built images from ECR — the EC2 box never builds).
#
# Required env vars (exported by the workflow before calling this script):
#   APP_IMAGE    — full ECR URI for the new app image   (…/boldmark-app:<sha>)
#   NGINX_IMAGE  — full ECR URI for the new nginx image (…/boldmark-nginx:<sha>)
#
# Expected on disk at /opt/boldmark/ (scp'd by the workflow):
#   - docker-compose.yml
#   - docker/mysql/my.cnf      (bind-mounted by the db service)
#   - .env                     (written by CI from the PRODUCTION_ENV secret)
#
# ECR auth: this box pulls using its attached IAM instance profile
# (boldmark-ec2-ecr-role → AmazonEC2ContainerRegistryReadOnly). No keys stored.
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail

DEPLOY_DIR="/opt/boldmark"
COMPOSE_FILE="$DEPLOY_DIR/docker-compose.yml"
AWS_REGION="${AWS_REGION:-eu-west-2}"
DOMAIN="portal.boldmarkprop.co.za"
CERT_EMAIL="ops@boldmarkprop.co.za"
HEALTH_TIMEOUT=90     # seconds to wait for app health after restart

cd "$DEPLOY_DIR"

# APP_IMAGE / NGINX_IMAGE are consumed by docker-compose.yml via shell
# interpolation. Exporting them here (and NOT passing --env-file) lets compose
# read the rest of the config — DB_*, REDIS_PASSWORD, etc. — from ./.env.
APP_IMAGE="${APP_IMAGE:?Error: APP_IMAGE env var required}"
NGINX_IMAGE="${NGINX_IMAGE:?Error: NGINX_IMAGE env var required}"
export APP_IMAGE NGINX_IMAGE

ECR_REGISTRY="${APP_IMAGE%%/*}"   # e.g. 123456789012.dkr.ecr.eu-west-2.amazonaws.com

compose() { docker compose -f "$COMPOSE_FILE" "$@"; }
step()    { echo ""; echo "▶ $1"; }
ok()      { echo "  ✓ $1"; }
fail()    { echo "  ✗ $1"; exit 1; }

cat <<EOF

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  BoldMark PMS — AWS/EC2 Deploy
  Time        : $(date -u +"%Y-%m-%dT%H:%M:%SZ")
  App image   : $APP_IMAGE
  Nginx image : $NGINX_IMAGE
  Registry    : $ECR_REGISTRY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOF

# ── 0/6: Authenticate to ECR (via instance-profile role) + pull images ─
step "[0/6] Logging in to ECR and pulling images..."
aws ecr get-login-password --region "$AWS_REGION" \
  | docker login --username AWS --password-stdin "$ECR_REGISTRY" >/dev/null
ok "ECR login OK"
compose pull
ok "Images pulled"

# ── 1/6: Ensure DB + Redis are up and healthy ────────────────────────
step "[1/6] Ensuring DB + Redis are healthy..."
compose up -d db redis
for i in $(seq 1 30); do
  if compose exec -T db mysqladmin ping --silent 2>/dev/null; then
    ok "DB is healthy"; break
  fi
  if [ "$i" = "30" ]; then fail "DB did not become healthy in 60s"; fi
  sleep 2
done

# ── 2/6: Recreate app with the new image ─────────────────────────────
step "[2/6] Recreating app container with new image..."
compose up -d --no-deps --force-recreate app
ok "App container recreated"

# ── 3/6: Wait for app health, abort deploy if it never recovers ──────
step "[3/6] Waiting for app health (timeout: ${HEALTH_TIMEOUT}s)..."
HEALTH_OK=false
for i in $(seq 1 $HEALTH_TIMEOUT); do
  if compose exec -T app /healthcheck.sh >/dev/null 2>&1; then
    HEALTH_OK=true; ok "App is healthy (took ${i}s)"; break
  fi
  sleep 1
done
if [ "$HEALTH_OK" != "true" ]; then
  echo ""; echo "Recent app logs:"; compose logs --tail=50 app || true
  fail "App did not become healthy in ${HEALTH_TIMEOUT}s — aborting deploy"
fi

# ── 4/6: Migrations + cache warm ─────────────────────────────────────
step "[4/6] Running migrations..."
compose exec -T app php artisan migrate --force
ok "Migrations complete"

step "[4/6] Warming caches..."
compose exec -T app bash -c "
  php artisan config:cache &&
  php artisan route:cache &&
  php artisan view:cache &&
  php artisan event:cache
"
ok "Caches warmed"

# ── 5/6: Start nginx, then issue / renew the Let's Encrypt cert ──────
# nginx starts with a self-signed placeholder (see nginx/docker-entrypoint.sh),
# serves the ACME challenge over :80, then we recreate it so it picks up the
# real cert. certonly is idempotent (--keep-until-expiring).
step "[5/6] Starting nginx + issuing SSL certificate..."
compose up -d --no-deps --force-recreate nginx
sleep 5
# --entrypoint certbot overrides the service's 12h renewal loop so certonly runs.
timeout 120 compose run --rm --entrypoint certbot certbot certonly \
    --webroot -w /var/www/certbot \
    -d "$DOMAIN" \
    --email "$CERT_EMAIL" \
    --agree-tos --no-eff-email \
    --keep-until-expiring --quiet 2>&1 || true
compose up -d --no-deps --force-recreate nginx
sleep 3
ok "Nginx up, SSL certificate ready"

# ── 6/6: Start horizon, scheduler, certbot renewer ───────────────────
step "[6/6] Starting horizon, scheduler, certbot renewer..."
compose up -d --no-deps --force-recreate horizon scheduler certbot
ok "All services running"

# ── SSL auto-renewal pickup ──────────────────────────────────────────
# certbot renews in the background every 12h; nginx must reload to pick up the
# new files. A daily 03:00 cron guarantees pickup within the 30-day window.
echo "0 3 * * * root cd $DEPLOY_DIR && docker compose exec -T nginx nginx -s reload >/dev/null 2>&1" \
  | sudo tee /etc/cron.d/boldmark-ssl-reload >/dev/null
sudo chmod 644 /etc/cron.d/boldmark-ssl-reload
ok "Daily nginx SSL reload cron installed"

# ── Cleanup: reclaim disk on the small box ───────────────────────────
docker image prune -af >/dev/null 2>&1 || true
docker builder prune -af >/dev/null 2>&1 || true

# ── External reachability check (best-effort, non-fatal) ─────────────
step "Checking external HTTPS..."
if curl -fsS --max-time 10 "https://$DOMAIN/up" >/dev/null 2>&1; then
  ok "https://$DOMAIN/up returned 200"
else
  echo "  ⚠ External HTTPS check failed (DNS/cert/cold-start — not aborting)"
fi

cat <<EOF

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✅ Deployment complete!
  Time : $(date -u +"%Y-%m-%dT%H:%M:%SZ")

  App:     https://$DOMAIN
  Horizon: https://$DOMAIN/horizon
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  First deploy only — seed roles, super admin & Passport keys:
    sudo bash $DEPLOY_DIR/docker/scripts/first-deploy.sh
EOF
