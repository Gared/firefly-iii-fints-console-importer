FROM php:8.5-cli@sha256:9ebdf4c28ab12c02085e171c31e22ac5f7bbb6a9f6927e3bc3dfe7ee23df51e0 AS base

COPY --from=composer/composer:2-bin@sha256:77522684c26b3910637d965f69e6a36e0382de6524bd98ad0604668329276901 /composer /usr/local/bin/composer

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
