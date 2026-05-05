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
# Keys land in storage/passport/ which is a named persistent volume,
# so they are generated only once and survive all future deploys.
if [ ! -f /var/www/html/storage/passport/oauth-private.key ]; then
    echo "── Generating Passport OAuth keys (first run) ──────────"
    php artisan passport:keys --force
    mv -f /var/www/html/storage/oauth-private.key /var/www/html/storage/passport/oauth-private.key 2>/dev/null || true
    mv -f /var/www/html/storage/oauth-public.key  /var/www/html/storage/passport/oauth-public.key  2>/dev/null || true
    echo "── Passport keys generated ─────────────────────────────"
fi

# oauth2-server requires key files to be 600/660, not world-readable.
# The chmod -R 775 above would have widened them — tighten back down.
chmod 600 /var/www/html/storage/passport/oauth-private.key \
          /var/www/html/storage/passport/oauth-public.key 2>/dev/null || true

echo "── Container ready. Starting PHP-FPM ────────────────────"

# Hand off to the CMD (php-fpm)
exec "$@"
