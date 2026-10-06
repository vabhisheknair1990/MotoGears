# Laravel API image (PHP 8.4 CLI + built-in server, suitable for local/demo use).
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip gd intl bcmath opcache exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN { echo "upload_max_filesize=20M"; echo "post_max_size=40M"; echo "memory_limit=512M"; } > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# Install dependencies first for better layer caching.
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && sed -i 's/\r$//' docker/entrypoint.sh && chmod +x docker/entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
