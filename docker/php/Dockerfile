FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev \
    libfreetype6-dev libonig-dev supervisor tini \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_pgsql pgsql zip bcmath intl opcache pcntl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 9000

ENTRYPOINT ["/usr/bin/tini","--"]
CMD ["php-fpm","-F"]
