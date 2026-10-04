# syntax=docker/dockerfile:1
#
# Production image, built by CI (.github/workflows/docker-publish.yml) and pushed to ghcr.io.
# Everything (composer install --no-dev, npm run build) happens HERE, at build time. Nothing is
# ever installed, built, migrated or seeded when the container starts or afterwards - the image
# is immutable and self-contained. In particular this image never runs migrations, fixtures or a
# schema drop/recreate: `/.docker-production-build` below is a marker file the Makefile refuses to
# run those targets against (see the "production safety" section of the Makefile).

FROM php:8.4-apache AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        zlib1g-dev libpng-dev libavif-dev libwebp-dev libjpeg-dev libxpm-dev libfreetype6-dev \
        libpq-dev \
        libzip-dev \
        libssl-dev \
        libicu-dev \
    && docker-php-ext-configure gd --enable-gd --with-jpeg --with-freetype --with-webp --with-avif --with-xpm \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        pdo \
        pdo_pgsql \
        zip \
        opcache \
        bcmath \
        intl \
    && rm -rf /var/lib/apt/lists/*

RUN ( \
    echo "opcache.enable=1"; \
    echo "opcache.jit_buffer_size=256M"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=8"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.fast_shutdown=1"; \
    ) >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# ---- composer dependencies (no-dev, optimized autoloader) ----
FROM php-base AS composer-build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-progress --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- front-end build (Vite) ----
# assets/app.js imports straight from vendor/ublaboo/datagrid/assets/ (the PHP package ships its
# own TS/JS), so this needs composer-build's vendor/ before it can run.
FROM node:22 AS frontend-build

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=composer-build /app/vendor ./vendor
RUN npm run build

# ---- runtime ----
FROM php-base AS runtime

# Baked in permanently: this is what the Makefile checks to refuse destructive DB targets.
# Deliberately NOT an ENV (docker run -e / compose `environment:` could override an ENV back to
# anything at container start) - a file written at build time cannot be un-written that way.
RUN touch /.docker-production-build

RUN a2enmod rewrite \
    && echo "ServerName web-project-web" >> /etc/apache2/apache2.conf

COPY .docker/web/config/php.ini /usr/local/etc/php/

WORKDIR /var/www/html

# config/env/prod.neon is overwritten by CI from the PRODUCTION_NEON secret right before this
# image is built (see .github/workflows/docker-publish.yml), so it is already part of this
# `COPY . .` - the version committed here is only a dev-safe placeholder (see AGENTS.md).
COPY --from=composer-build --chown=www-data:www-data /app /var/www/html
COPY --from=frontend-build --chown=www-data:www-data /app/www/assets /var/www/html/www/assets

RUN mkdir -p \
        temp/web/cache temp/web/mails \
        temp/console/cache \
        log/web log/console backup \
    && chown -R www-data:www-data temp log backup

EXPOSE 80
