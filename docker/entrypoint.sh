#!/bin/sh
# Prepares the app each time the container starts: database, app key, caches and migrations.
set -e
cd /var/www/html
mkdir -p /data

# SQLite database lives in /data so it survives container updates.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_DATABASE="${DB_DATABASE:-/data/database.sqlite}"
    export DB_DATABASE
    if [ ! -f "$DB_DATABASE" ]; then
        echo "Creating a new database at $DB_DATABASE"
        touch "$DB_DATABASE"
    fi
fi

# App key (encrypts sessions/cookies): use APP_KEY if given, otherwise create one once and keep it in /data.
if [ -z "$APP_KEY" ]; then
    if [ ! -s /data/app_key ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > /data/app_key
        echo "Generated a new app key in /data/app_key"
    fi
    APP_KEY="$(cat /data/app_key)"
    export APP_KEY
fi

# Bake the settings into Laravel's caches, then bring the database up to date.
php artisan config:cache --no-ansi
php artisan route:cache --no-ansi
php artisan view:cache --no-ansi
php artisan migrate --force --no-ansi

# Apache runs as www-data, so it needs to own the data and writable folders.
chown -R www-data:www-data /data storage bootstrap/cache

exec "$@"
