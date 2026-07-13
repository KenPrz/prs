# syntax=docker/dockerfile:1

########################################################################
# Stage 1 — install PHP deps, generate Wayfinder routes, build assets
########################################################################
FROM serversideup/php:8.4-cli AS build

USER root

# Extensions composer's platform check needs (phpword/fpdf → gd, medialibrary → exif)
RUN install-php-extensions gd exif

# Node 22 for the Vite build (the Wayfinder plugin shells out to `php artisan`)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && npm ci \
    && php artisan wayfinder:generate --with-form \
    && npm run build \
    && rm -rf node_modules

########################################################################
# Stage 2 — production runtime (nginx + php-fpm in one supervised image)
########################################################################
FROM serversideup/php:8.4-fpm-nginx AS app

USER root
RUN install-php-extensions pdo_pgsql gd zip exif

# Attachment uploads are capped at 10 MB per file, 20 files per request.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_POST_MAX_SIZE=64M \
    PHP_UPLOAD_MAX_FILE_SIZE=12M

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

USER www-data
