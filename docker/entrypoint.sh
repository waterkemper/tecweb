#!/bin/sh
set -e

cd /var/www/html

# Wait for postgres
until php artisan db:show 2>/dev/null; do
  echo "Waiting for database..."
  sleep 2
done

# Run migrations
php artisan migrate --force

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
