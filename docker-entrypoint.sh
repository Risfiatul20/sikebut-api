#!/bin/sh
set -e

# Pastikan folder runtime storage selalu ada
mkdir -p /var/www/storage/framework/cache \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/app/public \
         /var/www/storage/logs

# Hanya jalankan cache artisan pada container web API (php-fpm), bukan worker/scheduler
if [ "$1" = "php-fpm" ]; then
    echo "Optimasi cache Laravel..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
