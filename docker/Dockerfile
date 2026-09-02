FROM php:8.5-cli@sha256:0e17ef0527f296b85bfe4cfb5219b29cafc37224857ed73d28628ea142930ac8 AS base

COPY --from=composer/composer:2-bin@sha256:536116acd18cd2d99d0351c1dfded22c83f34cef481d83f2747ff2cbca7587cd /composer /usr/local/bin/composer

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
