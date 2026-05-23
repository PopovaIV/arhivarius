FROM php:8.3-fpm-alpine

# Pre-built PHP extensions — no C compilation, drastically faster than pecl/docker-php-ext-install
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache \
        git \
        unzip \
        poppler-utils \
        djvulibre \
    && install-php-extensions \
        intl \
        pdo_pgsql \
        zip \
        opcache \
        bcmath \
        redis

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

RUN addgroup -g 1000 app && adduser -u 1000 -G app -D app
USER app
