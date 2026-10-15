# Laravel (admin panel, API, queue worker, scheduler). Build context: ./backend
FROM serversideup/php:8.4-fpm-nginx

USER root
# gd: the QR bill draws the Swiss cross into the QR code (not declared by Composer).
RUN install-php-extensions intl bcmath gd

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html

# Dependencies first for better layer caching.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY --chown=www-data:www-data . .

# Autoloader, package discovery and Filament's published assets (git-ignored).
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan filament:assets \
    && chown -R www-data:www-data /var/www/html/public /var/www/html/bootstrap/cache /var/www/html/storage

USER www-data
