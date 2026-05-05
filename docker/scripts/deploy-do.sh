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

# ── 5/5: Start remaining services ────────────────────────────────────
step "[5/5] Starting nginx, horizon, scheduler..."
docker compose up -d --force-recreate nginx horizon scheduler
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
echo "  App:     http://$PUBLIC_IP"
echo "  Horizon: http://$PUBLIC_IP/horizon"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
