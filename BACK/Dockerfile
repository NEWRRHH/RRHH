# syntax=docker/dockerfile:1

FROM php:8.5-fpm

# install system dependencies and PHP extensions required by Laravel
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       git \
       curl \
       zip \
       unzip \
       libpng-dev \
       libjpeg-dev \
       libfreetype6-dev \
       libonig-dev \
       libxml2-dev \
       libzip-dev \
       libicu-dev \
       libpq-dev \
       sqlite3 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd intl mbstring bcmath xml pcntl zip pdo pdo_mysql pdo_pgsql \
    && pecl install redis || true \
    && docker-php-ext-enable redis || true \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# install composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# set working directory
WORKDIR /var/www/html

# copy app (during development you'll mount the volume instead)
COPY . /var/www/html

# ensure the Laravel Reverb package is available inside the image
# (container build will already read composer.json, but running require here
#  makes builds idempotent when composer.json is edited manually later)
RUN composer require laravel/reverb:^1.7 --no-interaction || true

# ensure storage and cache directories exist with correct permissions
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# expose php-fpm port
EXPOSE 9000

# copy runtime entrypoint (fix perms, install composer deps if missing)
COPY docker/entrypoint.sh /usr/local/bin/docker-start.sh
RUN chmod +x /usr/local/bin/docker-start.sh

ENTRYPOINT ["/usr/local/bin/docker-start.sh"]

# default command (will be exec'd by the entrypoint)
CMD ["php-fpm"]
