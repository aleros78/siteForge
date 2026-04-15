#!/bin/bash
set -e

echo "==> SiteForge entrypoint starting..."

# Attendi il DB se necessario (già gestito da healthcheck in compose)
echo "==> Waiting for database..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    echo "    Database not ready, retrying in 2s..."
    sleep 2
done
echo "    Database OK"

# Key generation se mancante
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --force
fi

# Storage link
echo "==> Creating storage link..."
php artisan storage:link --force 2>/dev/null || true

# Run migrations
echo "==> Running migrations..."
php artisan migrate --force --no-interaction

# Seed iniziale solo se tabelle vuote
echo "==> Running seeders..."
php artisan db:seed --force --no-interaction 2>/dev/null || true

# Optimize per production
if [ "$APP_ENV" = "production" ]; then
    echo "==> Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Ensure storage directories
mkdir -p /var/www/storage/app/backups
mkdir -p /var/www/storage/logs
chmod -R 775 /var/www/storage /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

echo "==> SiteForge ready!"
exec "$@"
