#!/bin/sh
# Container start: prepare Laravel, start the scheduler, then run Apache in the foreground.
set -e
cd /var/www/html

# Apache must listen on the port the host gives us (Render: $PORT, default 10000).
sed -i "s/^Listen 80$/# Listen 80 (replaced by docker\/apache.conf)/" /etc/apache2/ports.conf

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Runtime folders (the disk is fresh on every start on Render's free plan).
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs storage/app/private storage/app/public bootstrap/cache

# Cache config, routes and views with this environment's variables.
php artisan config:cache
php artisan route:cache
php artisan view:cache
[ -e public/storage ] || [ "$FILES_DRIVER" = "firebase" ] || php artisan storage:link

# Everything Laravel writes at runtime belongs to the web server user.
chown -R www-data:www-data storage bootstrap/cache

# Laravel scheduler (monthly price update, daily malware re-scan), as the web user.
# On Render's free plan it only runs while the service is awake.
if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
    su -s /bin/sh www-data -c "php artisan schedule:work" > /proc/1/fd/1 2>&1 &
fi

exec apache2-foreground
