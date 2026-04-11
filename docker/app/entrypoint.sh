#!/bin/sh
set -e

# Sync built public assets into the shared volume.
# Named volumes are only auto-populated on first creation, so we
# must refresh them on every container start after a rebuild.
if [ -d /var/www/public-build ]; then
    cp -a /var/www/public-build/. /var/www/public/
fi

# Cache config/routes/views on first boot
if [ "$1" = "php-fpm" ] || [ "$1" = "php" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    # Run migrations automatically (safe with --force)
    php artisan migrate --force
fi

exec "$@"
