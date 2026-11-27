FROM php:8.5.13-cli@sha256:f324fcb4dc009ac0470a7d614cd122499dcee3ca47a9bab620fc14c0eb759ba2 AS base

COPY --from=composer:2.11.3@sha256:5b2137f112f939a92788ceb5e55f54859d9dbf467a44037cf7760bdda949f365 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y unzip libicu-dev  \
    && docker-php-ext-configure intl && docker-php-ext-install intl

WORKDIR /app

FROM base AS development

ENV APP_ENV=dev APP_DEBUG=1 COMPOSER_HOME=/tmp

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

FROM base AS production

ENV APP_ENV=prod APP_DEBUG=0

COPY . /app

RUN composer install --no-dev --no-interaction --optimize-autoloader
