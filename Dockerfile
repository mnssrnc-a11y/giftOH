# Gift of Hope — production image (Render, Railway, or any Docker host).
#
# Stage 1 builds the Vite assets, stage 2 installs Composer packages, stage 3 is the PHP 8.2 +
# Apache runtime. PHP 8.2 is required: kreait/firebase-php 7.0 does not support PHP 8.3+.

# ---------- 1. Front-end assets ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------- 2. PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-reqs \
    && composer dump-autoload --no-dev --no-scripts --optimize

# ---------- 3. Runtime ----------
FROM php:8.2-apache

# OPcache for speed; everything else Laravel and Firebase need ships with the official image.
RUN docker-php-ext-install opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-giftofhope.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs storage/app/private storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi \
    && chmod +x docker/entrypoint.sh

ENV PORT=10000 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 10000
ENTRYPOINT ["docker/entrypoint.sh"]
