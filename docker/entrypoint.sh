#!/bin/sh
set -e

cd /var/www/html

if [ "$1" = "supervisord" ]; then
    if [ -z "$APP_KEY" ]; then
        echo "APP_KEY belum diisi. Buat dengan: docker compose run --rm app php artisan key:generate --show" >&2
        exit 1
    fi

    mkdir -p "$(dirname "$DB_DATABASE")" storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
    touch "$DB_DATABASE"
    chown -R www-data:www-data "$(dirname "$DB_DATABASE")" storage bootstrap/cache

    php artisan storage:link --force --no-interaction
    php artisan migrate --force --no-interaction

    if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
        php artisan db:seed --class=AdminSeeder --force --no-interaction
    fi

    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction

    chown -R www-data:www-data storage bootstrap/cache "$(dirname "$DB_DATABASE")"
fi

exec "$@"
