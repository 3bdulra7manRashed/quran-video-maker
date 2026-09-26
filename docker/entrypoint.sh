#!/bin/bash
set -e

# Ensure essential storage directories exist
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/storage/quran-video-data/audio \
         /var/www/html/storage/quran-video-data/backgrounds \
         /var/www/html/storage/quran-video-data/debug \
         /var/www/html/storage/quran-video-data/rendered_segments \
         /var/www/html/storage/quran-video-data/temp \
         /var/www/html/storage/quran-video-data/videos \
         /var/www/html/bootstrap/cache

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate APP_KEY if empty
if [ -z "$APP_KEY" ]; then
    echo "[Entrypoint] Notice: APP_KEY is empty, generating key..."
    php artisan key:generate --force
fi

# Ensure storage link exists
php artisan storage:link --force 2>/dev/null || true

# If DB is configured, wait for it and run migrations (only on web service to prevent concurrency issues)
if [ -n "$DB_HOST" ] && [ "$RUN_MIGRATIONS" != "false" ] && [ "$1" = "/usr/bin/supervisord" ]; then
    echo "[Entrypoint] Waiting for database connection at $DB_HOST..."
    PORT="${DB_PORT:-3306}"
    
    until php -r "
        try {
            new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . ('$PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        echo "[Entrypoint] Database not ready yet, sleeping 3s..."
        sleep 3
    done
    
    echo "[Entrypoint] Database connected! Running migrations..."
    php artisan migrate --force || true
fi

# Clear any stale caches so environment variables take effect
php artisan optimize:clear 2>/dev/null || true

echo "[Entrypoint] Ready. Starting process: $@"
exec "$@"
