# ==========================================
# STAGE 1: Build Frontend Assets (Node.js 24)
# ==========================================
#FROM node:24-alpine AS frontend-builder
#WORKDIR /var/www
#COPY package.json ./
#RUN --mount=type=cache,target=/root/.npm \
#    npm install --no-progress
#COPY . .
#RUN npm run build

# ==========================================
# STAGE 2: Install composer dependencies (PHP 8.3)
# ==========================================
FROM composer:2 AS composer-base
WORKDIR /var/www
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer \
    composer install \
    --no-dev \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --no-autoloader
# Salin seluruh kode program aplikasi
COPY . .
# Buat Autoloader yang optimal di stage ini karena Composer tersedia di sini
RUN --mount=type=cache,target=/root/.composer \
    mkdir -p bootstrap/cache && chmod 775 bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --classmap-authoritative

# ==========================================
# STAGE 3: Production Environment (PHP 8.3)
# ==========================================
FROM php:8.3-fpm-alpine
WORKDIR /var/www

# Argumen untuk menyamakan UID/GID (Default ke 1000 jika tidak diisi)
# ARG USER_ID=1000
# ARG GROUP_ID=1000

# Install system dependencies & PHP extensions untuk PostgreSQL & Queue (Redis/Database)
RUN apk add --no-cache curl fcgi libpng-dev zip unzip libpq-dev libzip-dev autoconf build-base \
    && docker-php-ext-install pdo_pgsql bcmath gd opcache zip pcntl posix \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf build-base \
    && apk add --no-cache su-exec

# Gunakan konfigurasi produksi bawaan PHP sebagai dasar
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/php-production.ini

# Copy hasil install composer dependencies dari Stage 2 ke container
COPY --from=composer-base --chown=www-data:www-data /var/www /var/www

# Copy hasil build Vite dari Stage 1 ke folder public aplikasi
# COPY --from=frontend-builder --chown=www-data:www-data /var/www/public/build ./public/build

COPY --chown=www-data:www-data docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Setup Direktori & Permission secara ketat
RUN mkdir -p /var/www/bootstrap/cache \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/app/public \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Pindah ke user aman (www-data) untuk runtime keamanan
USER www-data

EXPOSE 9000

# Override ENTRYPOINT
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
