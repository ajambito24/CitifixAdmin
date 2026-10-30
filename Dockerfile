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

# Build vendor/ on the server (no dev packages)
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html

EXPOSE 10000