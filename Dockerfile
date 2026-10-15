# syntax=docker/dockerfile:1

# ---- 1. PHP dependencies --------------------------------------------------------------------
# Composer packages are plain PHP, so this stage runs once on the build machine's own platform
# rather than under emulation for each Raspberry Pi architecture.
FROM --platform=$BUILDPLATFORM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---- 2. The app: PHP 8.3 + Apache -----------------------------------------------------------
FROM php:8.3-apache

# SQLite support is built in; add MySQL support (in case you move to MySQL) and OPcache for speed.
RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=64'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'expose_php=Off' > /usr/local/etc/php/conf.d/security.ini

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/aetracker-entrypoint

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN chmod +x /usr/local/bin/aetracker-entrypoint \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

# Defaults - override any of these in docker-compose.yml. The SQLite database (and the app key, if
# you don't set APP_KEY) live in /data, so mount a volume there to keep your data.
ENV APP_NAME="AE Tracker" \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_TIMEZONE=UTC \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync

VOLUME /data
EXPOSE 80

HEALTHCHECK --interval=1m --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost/up > /dev/null || exit 1

ENTRYPOINT ["aetracker-entrypoint"]
CMD ["apache2-foreground"]
