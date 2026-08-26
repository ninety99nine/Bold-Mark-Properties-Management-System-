#!/bin/sh
# ──────────────────────────────────────────────────────────────────────────
# Dev API entrypoint: install deps → wait for DB → migrate → seed once → serve.
# DB_HOST/DB_PORT/REDIS_HOST are injected by docker-compose.dev.yml and override
# the values in api/.env (Laravel's Dotenv does not overwrite real env vars).
# ──────────────────────────────────────────────────────────────────────────
set -e
cd /var/www/html

# 1. Composer deps (only if missing — the bind-mounted vendor usually exists)
if [ ! -f vendor/autoload.php ]; then
  echo "▶ Installing composer dependencies..."
  composer install --no-interaction --prefer-dist
fi

# 2. App key + Passport keys (only if missing — safe on a fresh checkout)
grep -q '^APP_KEY=base64' .env 2>/dev/null || php artisan key:generate --force || true
[ -f storage/oauth-private.key ] || php artisan passport:keys --force 2>/dev/null || true

# 3. Wait for MySQL to accept connections
echo "▶ Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until php -r '
  $h=getenv("DB_HOST"); $p=getenv("DB_PORT")?:3306;
  $u=getenv("DB_USERNAME"); $w=getenv("DB_PASSWORD"); $d=getenv("DB_DATABASE");
  try { new PDO("mysql:host=$h;port=$p;dbname=$d",$u,$w); exit(0); }
  catch (Exception $e) { exit(1); }' 2>/dev/null; do
  sleep 2
done
echo "  ✓ MySQL ready"

# 4. Migrate (always) + seed once (only when there are no users yet)
php artisan migrate --force
USERS=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tr -dc '0-9')
if [ "${USERS:-0}" = "0" ]; then
  echo "▶ Empty DB — seeding ProductionSeeder..."
  php artisan db:seed --class=ProductionSeeder --force || true
fi

# 5. Clear stale caches so live code changes take effect immediately
php artisan optimize:clear >/dev/null 2>&1 || true

echo "▶ Laravel dev server → http://localhost:8000"
exec php artisan serve --host 0.0.0.0 --port 8000
