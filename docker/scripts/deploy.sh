#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
# Deployment Script — BoldMark PMS
# ═══════════════════════════════════════════════════════════════════════
# Pulls new images, restarts services, runs migrations, warms caches.
# Called by the GitHub Actions deploy job after image build & push.
#
# Required env vars (set by CI before calling this script):
#   APP_IMAGE   — full ECR URI for the new app image
#   NGINX_IMAGE — full ECR URI for the new nginx image
#
# Expected on disk at /opt/boldmark/:
#   - docker-compose.yml
#   - .env  (production env, written by CI from PRODUCTION_ENV secret)
#   - nginx/  docker/  (synced from repo by CI)
#
# Behavior:
#   - App container restart: ~5s downtime (acceptable for low-traffic SaaS)
#   - Migrations run AFTER app restart so new code is in place
#   - Cache warm runs LAST so it picks up cached config
#   - Aborts deploy if health check fails post-restart (prevents bad rollouts)
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail

DEPLOY_DIR="/opt/boldmark"
COMPOSE_FILE="$DEPLOY_DIR/docker-compose.yml"
DEPLOY_ENV="$DEPLOY_DIR/.deploy.env"
HEALTH_TIMEOUT=60     # seconds to wait for health check after app restart

APP_IMAGE="${APP_IMAGE:?Error: APP_IMAGE env var required}"
NGINX_IMAGE="${NGINX_IMAGE:?Error: NGINX_IMAGE env var required}"

compose() {
  docker compose -f "$COMPOSE_FILE" --env-file "$DEPLOY_ENV" "$@"
}

step()  { echo ""; echo "▶ $1"; }
ok()    { echo "  ✓ $1"; }
fail()  { echo "  ✗ $1"; exit 1; }

cat <<EOF

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  BoldMark PMS — Deploy
  Time        : $(date -u +"%Y-%m-%dT%H:%M:%SZ")
  App image   : $APP_IMAGE
  Nginx image : $NGINX_IMAGE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOF

# ── Persist image refs for compose ───────────────────────────────────
cat > "$DEPLOY_ENV" <<EOF
APP_IMAGE=$APP_IMAGE
NGINX_IMAGE=$NGINX_IMAGE
EOF

# ── 1/5: Ensure DB + Redis are up (no-op if already running) ─────────
step "[1/5] Ensuring DB + Redis are healthy..."
compose up -d db redis
# Wait up to 60s for DB health
for i in $(seq 1 30); do
  if compose ps --status running --format json 2>/dev/null | grep -q '"db"'; then
    if compose exec -T db mysqladmin ping --silent 2>/dev/null; then
      ok "DB is healthy"
      break
    fi
  fi
  if [ "$i" = "30" ]; then fail "DB did not become healthy in 60s"; fi
  sleep 2
done

# ── 2/5: Restart app with new image ──────────────────────────────────
step "[2/5] Recreating app container with new image..."
compose up -d --no-deps --force-recreate app
ok "App container recreated"

# ── 3/5: Wait for app health, abort if it never becomes healthy ──────
step "[3/5] Waiting for app health (timeout: ${HEALTH_TIMEOUT}s)..."
HEALTH_OK=false
for i in $(seq 1 $HEALTH_TIMEOUT); do
  if compose exec -T app /healthcheck.sh >/dev/null 2>&1; then
    HEALTH_OK=true
    ok "App is healthy (took ${i}s)"
    break
  fi
  sleep 1
done
if [ "$HEALTH_OK" != "true" ]; then
  echo ""
  echo "Recent app logs:"
  compose logs --tail=50 app || true
  fail "App did not become healthy in ${HEALTH_TIMEOUT}s — aborting deploy"
fi

# ── 4/5: Run migrations + recreate nginx, horizon, scheduler ─────────
step "[4/5] Running migrations..."
compose exec -T app php artisan migrate --force
ok "Migrations complete"

step "[4/5] Recreating nginx, horizon, scheduler..."
compose up -d --no-deps --force-recreate nginx horizon scheduler
ok "Auxiliary services recreated"

# ── 5/5: Warm caches (config, route, view, event) ────────────────────
step "[5/5] Warming caches..."
compose exec -T app bash -c "
  php artisan config:cache &&
  php artisan route:cache &&
  php artisan view:cache &&
  php artisan event:cache
"
ok "Caches warmed"

# ── HTTPS reachability check (best-effort) ───────────────────────────
step "Checking external HTTPS..."
if curl -fsS --max-time 10 https://portal.boldmarkprop.co.za/up >/dev/null 2>&1; then
  ok "https://portal.boldmarkprop.co.za/up returned 200"
else
  echo "  ⚠ External HTTPS check failed (may be DNS/cert/cold-start related — not aborting)"
fi

cat <<EOF

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✅ Deployment complete!
  Time : $(date -u +"%Y-%m-%dT%H:%M:%SZ")

  Verify:
    https://portal.boldmarkprop.co.za
    https://portal.boldmarkprop.co.za/horizon
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOF
