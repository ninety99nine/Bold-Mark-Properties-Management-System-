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

echo "▶ Seeding roles and permissions..."
run php artisan db:seed --class=RolesAndPermissionsSeeder

echo "▶ Seeding super admin account..."
run php artisan db:seed --class=SuperAdminSeeder

echo "▶ Installing Laravel Passport (creates OAuth clients)..."
run php artisan passport:install --force

echo "▶ Publishing Horizon assets..."
run php artisan horizon:install

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
