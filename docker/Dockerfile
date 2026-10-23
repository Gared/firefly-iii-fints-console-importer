FROM php:8.5.12-cli@sha256:635a4b998025ab269194639a5549c6fdcd9b4f88ec8cd761b092de8653d40663 AS base

COPY --from=composer:2.10.3@sha256:d0c32820c62cc860fddd22adceed4eeda7698b093a7a88d503414172ce400626 /usr/bin/composer /usr/local/bin/composer

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
