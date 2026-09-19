FROM php:8.5.10-cli@sha256:df50257c90ad9a53052fb4e9c6fdba8262cf72ca9e9a048b7c89f74c8d23fc0d AS base

COPY --from=composer/composer:2.10.3-bin@sha256:24ff31fd48a5bffb7fa97a29195e8f3bed4c0ee728dcae6335a0c0b5acfaca60 /composer /usr/local/bin/composer

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
