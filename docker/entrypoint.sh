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

# MySQL/MariaDB/PostgreSQL: wait for the database to accept connections (it may still be starting).
if [ "${DB_CONNECTION:-sqlite}" != "sqlite" ]; then
    echo "Waiting for the ${DB_CONNECTION} database at ${DB_HOST:-127.0.0.1}..."
    tries=0
    until php -r '
        $driver = getenv("DB_CONNECTION") === "pgsql" ? "pgsql" : "mysql";
        $port = getenv("DB_PORT") ?: ($driver === "pgsql" ? 5432 : 3306);
        new PDO("$driver:host=".(getenv("DB_HOST") ?: "127.0.0.1").";port=$port", getenv("DB_USERNAME") ?: "root", getenv("DB_PASSWORD") ?: "");
    ' 2>/dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "Couldn't connect to the database after 2 minutes - check the DB_* settings." >&2
            exit 1
        fi
        sleep 2
    done
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
