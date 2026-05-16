FROM php:8.1-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype-dev libwebp-dev \
        libicu-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql intl mbstring zip opcache

# Xdebug for the dev container. Configured to xdebug.mode=off by default
# in php.ini so production-equivalent benchmarking reflects real PHP speed. The
# test runner overrides this per-invocation with XDEBUG_MODE=coverage when
# `phpunit --coverage-text` (or HTML) is requested.
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
