# CitiFix admin panel - Render (Docker runtime)
# Same PHP version as local, so composer.lock installs without conflicts.
FROM php:8.5-apache

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PORT=10000

# Extensions: curl, fileinfo, mbstring, dom and xml already ship with the image.
# gd + zip are needed by the Word/PDF export (PhpWord, Dompdf).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production PHP settings (errors hidden), Manila timezone, room for announcement photos
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "date.timezone=Asia/Manila\nupload_max_filesize=10M\npost_max_size=12M\nmemory_limit=256M\n" \
       > "$PHP_INI_DIR/conf.d/citifix.ini"

# Render tells the app which port to use through $PORT; make Apache listen on it
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . .

# Build vendor/ on the server (no dev packages).
# ext-grpc is skipped on purpose: config/firebase.php forces REST transport.
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-grpc \
    && chown -R www-data:www-data /var/www/html

EXPOSE 10000

# Render mounts secret files read-only and readable by root only, but Apache runs PHP
# as www-data. At start-up, copy the key to a private folder outside the web root,
# give it to www-data (owner-only), then start Apache as usual.
CMD ["sh", "-c", "mkdir -p /var/citifix && cp /etc/secrets/service-account.json /var/citifix/service-account.json && chown www-data:www-data /var/citifix/service-account.json && chmod 600 /var/citifix/service-account.json || echo 'WARNING: could not prepare service-account.json'; exec apache2-foreground"]