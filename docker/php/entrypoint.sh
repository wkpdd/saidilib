#!/bin/bash
set -e

cd /var/www/html

# Install PHP deps if missing (first boot)
if [ ! -d vendor ]; then
  composer install --no-interaction --no-progress
fi

# Ensure .env + app key
if [ ! -f .env ]; then
  cp .env.example .env
fi
if ! grep -q "^APP_KEY=base64" .env; then
  php artisan key:generate --force || true
fi

# Storage symlink + permissions
php artisan storage:link 2>/dev/null || true
mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Wait for DB, then migrate + seed
echo "Waiting for database..."
until php -r "new PDO('mysql:host=db;port=3306', 'root', 'root');" 2>/dev/null; do
  sleep 2
done
php artisan migrate --force --seed || php artisan migrate --force || true

# Pre-generate responsive WebP thumbnails for any locally-stored images
php artisan images:thumbnails || true

exec "$@"
