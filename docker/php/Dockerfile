FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libicu-dev \
        libonig-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_mysql mbstring bcmath gd intl zip exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN groupmod -o -g 1000 www-data && usermod -o -u 1000 www-data
