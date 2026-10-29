# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist \
        --ignore-platform-req=ext-zip --ignore-platform-req=ext-intl
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts \
        --ignore-platform-req=ext-zip --ignore-platform-req=ext-intl
RUN php artisan package:discover --ansi

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /app/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache nginx supervisor libzip icu-libs sqlite-libs oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev icu-dev sqlite-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" zip intl mbstring opcache pcntl pdo_sqlite \
    && apk del .build-deps

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync

RUN mkdir -p /data storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /data storage bootstrap/cache

COPY --from=vendor --chown=www-data:www-data /app/bootstrap/cache/ ./bootstrap/cache/

VOLUME ["/data", "/var/www/html/storage/app"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -q --spider http://127.0.0.1/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
