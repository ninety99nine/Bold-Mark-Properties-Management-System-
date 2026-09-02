#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
# First-Deploy Setup Script
# ═══════════════════════════════════════════════════════════════════
# Run this ONCE after the very first deployment to production.
# It seeds the database and sets up Laravel Passport.
#
# Prerequisites:
#   - docker compose up -d has been run and all containers are healthy
#   - /opt/boldmark/.env is configured correctly
#
# Usage:
#   bash /opt/boldmark/scripts/first-deploy.sh
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

DEPLOY_DIR="/opt/boldmark"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  BoldMark PMS — First-Deploy Setup"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

run() {
    docker compose -f "$DEPLOY_DIR/docker-compose.yml" exec -T app "$@"
}

echo "▶ Running database migrations..."
run php artisan migrate --force

echo "▶ Seeding production data (Passport client, roles, org, admin users)..."
# ProductionSeeder is idempotent (firstOrCreate / updateOrCreate) and also
# creates the Passport personal-access client, so it is safe to re-run.
run php artisan db:seed --class=ProductionSeeder --force

echo "▶ Warming application caches..."
run bash -c "php artisan config:cache && \
             php artisan route:cache && \
             php artisan view:cache && \
             php artisan event:cache"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ First-deploy setup complete!"
echo ""
echo "  Login at: https://portal.boldmarkprop.co.za"
echo "  Horizon:  https://portal.boldmarkprop.co.za/horizon"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
