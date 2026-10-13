#!/bin/sh
set -e

# Buat file database SQLite jika belum ada
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Run database migrations
php artisan migrate --force

# Run initial seeder if users table is empty
php artisan db:seed --force || true

# Start php-fpm in background
php-fpm -D

# Start nginx in foreground
nginx -g "daemon off;"
