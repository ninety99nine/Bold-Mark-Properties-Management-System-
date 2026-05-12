#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
# DigitalOcean Deploy Script — BoldMark PMS
# Builds Docker images on-server (no registry), restarts services,
# runs migrations, and warms caches.
# Called by GitHub Actions after rsyncing source to /opt/boldmark.
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail

DEPLOY_DIR="/opt/boldmark"
cd "$DEPLOY_DIR"

step() { echo ""; echo "▶ $1"; }
ok()   { echo "  ✓ $1"; }
fail() { echo "  ✗ $1"; exit 1; }

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  BoldMark PMS — DigitalOcean Deploy"
echo "  Time: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── 1/5: Build Docker images on server ───────────────────────────────
step "[1/5] Building Docker images (this takes a few minutes on first run)..."
docker compose build
ok "Images built"

# ── 2/5: Start DB + Redis, wait for DB to be healthy ─────────────────
step "[2/5] Starting DB and Redis..."
docker compose up -d db redis

for i in $(seq 1 30); do
  if docker compose exec -T db mysqladmin ping --silent 2>/dev/null; then
    ok "DB is healthy"
    break
  fi
  if [ "$i" = "30" ]; then fail "DB did not become healthy in 60s"; fi
  sleep 2
done

# ── 3/5: Restart app container with new image ────────────────────────
step "[3/5] Restarting app container..."
docker compose up -d --force-recreate app
sleep 10
ok "App container started"

# ── 4/5: Run migrations + warm caches ────────────────────────────────
step "[4/5] Running migrations..."
docker compose exec -T app php artisan migrate --force
ok "Migrations complete"

step "[4/5] Warming caches..."
docker compose exec -T app bash -c "
  php artisan config:cache &&
  php artisan route:cache &&
  php artisan view:cache &&
  php artisan event:cache
"
ok "Caches warmed"

# ── 5/6: Start nginx first (uses self-signed placeholder if no cert yet) ─
step "[5/6] Starting nginx..."
docker compose up -d --force-recreate nginx
sleep 5
ok "Nginx started"

# ── 5.5/6: Issue / renew SSL certificate ──────────────────────────────
step "[5.5/6] SSL certificate (Let's Encrypt)..."
# --entrypoint certbot overrides the compose service's renewal-loop entrypoint
# so that certonly actually runs instead of the 12-hour sleep loop.
timeout 120 docker compose run --rm --entrypoint certbot certbot certonly \
    --webroot -w /var/www/certbot \
    -d portal.boldmarkprop.co.za \
    --email ops@boldmarkprop.co.za \
    --agree-tos --no-eff-email \
    --keep-until-expiring --quiet 2>&1 || true
# Restart nginx so the entrypoint re-runs and symlinks /etc/nginx/ssl/ to the real cert.
# A plain reload is not enough — the entrypoint only runs at container start.
docker compose up -d --force-recreate nginx
sleep 5
ok "SSL certificate ready"

# ── 6/6: Start remaining services ─────────────────────────────────────
step "[6/6] Starting horizon, scheduler, certbot renewer..."
docker compose up -d --force-recreate horizon scheduler certbot
ok "All services running"

# Cleanup old layers
docker image prune -f 2>/dev/null || true

PUBLIC_IP=$(curl -s --max-time 5 http://169.254.169.254/metadata/v1/interfaces/public/0/ipv4/address 2>/dev/null \
  || curl -s --max-time 5 ifconfig.me 2>/dev/null \
  || echo "YOUR_DROPLET_IP")

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ Deployment complete!"
echo "  Time: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo ""
echo "  App:     https://portal.boldmarkprop.co.za"
echo "  Horizon: https://portal.boldmarkprop.co.za/horizon"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
