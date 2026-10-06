#!/bin/sh
set -e
cd /var/www/html

[ -f .env ] || cp .env.example .env

# Docker Compose injects DB_* etc. as environment variables; they override .env values.
if ! grep -q '^APP_KEY=base64' .env; then
  php artisan key:generate --force
fi

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
for i in $(seq 1 60); do
  if php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0);} catch (Throwable $e) { exit(1);}'; then
    break
  fi
  sleep 2
done

php artisan config:clear >/dev/null
php artisan migrate --force

# First boot only (empty database):
#   SEED_DEMO_DATA=true  (default, local) → full demo store: products, orders, customers, demo logins.
#   SEED_DEMO_DATA=false (production)     → roles, settings, categories, brands, vehicles and the
#                                            admin from ADMIN_EMAIL / ADMIN_PASSWORD — nothing fake.
count() { php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo \Illuminate\Support\Facades\DB::table($argv[1])->count();' "$1"; }
if [ "${SEED_DEMO_DATA:-true}" = "true" ]; then
  if [ "$(count products)" = "0" ]; then
    echo "Seeding demo data..."
    php artisan db:seed --force
  fi
elif [ "$(count roles)" = "0" ]; then
  echo "Setting up a fresh production store (no demo data)..."
  php artisan db:seed --class=ProductionSeeder --force
fi

php artisan storage:link >/dev/null 2>&1 || true

# Background queue worker (product imports). Restarted automatically if it exits;
# --max-time recycles it hourly so code/config changes are picked up.
( while true; do php artisan queue:work --sleep=2 --tries=1 --timeout=3600 --max-time=3600 --memory=512 --quiet; sleep 2; done ) &

# Run the scheduler (auto-cancels unpaid orders) in the background.
( while true; do php artisan schedule:run --no-interaction >/dev/null 2>&1; sleep 60; done ) &

export PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-6}
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
