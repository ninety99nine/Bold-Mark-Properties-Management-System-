#!/bin/bash
# Container entrypoint — runs before php-fpm starts.
# Sets up storage directories, symlinks, and Passport keys on first boot.
set -e

echo "── BoldMark PMS container starting ──────────────────────"

# Ensure all required storage directories exist and are writable.
# These are bind-mounted volumes shared between blue and green slots.
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/passport \
    /var/www/html/bootstrap/cache

chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

chmod -R 775 \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# Create public/storage symlink (storage:link).
# The --quiet flag suppresses "already exists" output on subsequent starts.
php artisan storage:link --quiet 2>/dev/null || true

# Generate Laravel Passport OAuth keys if they don't yet exist.
# Keys are stored in a named volume shared by both blue and green slots,
# so they are generated only once and persist across all deploys.
if [ ! -f /var/www/html/storage/passport/oauth-private.key ]; then
    echo "── Generating Passport OAuth keys (first run) ──────────"
    php artisan passport:keys --force
    echo "── Passport keys generated ─────────────────────────────"
fi

echo "── Container ready. Starting PHP-FPM ────────────────────"

# Hand off to the CMD (php-fpm)
exec "$@"
