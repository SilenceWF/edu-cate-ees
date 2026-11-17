#!/bin/sh
set -e
cd /var/www/html

# Copy built assets into the volume shared with nginx (so nginx serves the current build).
if [ -d /var/www/public-shared ]; then
    cp -r public/. /var/www/public-shared/
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
