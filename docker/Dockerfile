FROM php:8.5.12-cli@sha256:50956f094e90a716d43bea28087d447555ad80510e386ab8c9c10d8c70d781a9 AS base

COPY --from=composer:2.11.1@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c /usr/bin/composer /usr/local/bin/composer

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
