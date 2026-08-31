#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
  php artisan key:generate --force
fi

php artisan config:cache
php artisan migrate --force

if [ "$RUN_SEED" = "true" ]; then
  php artisan db:seed --force || true
fi

exec php artisan serve --host=0.0.0.0 --port=8000
