FROM php:8.5.12-cli@sha256:50956f094e90a716d43bea28087d447555ad80510e386ab8c9c10d8c70d781a9 AS base

COPY --from=composer:2.11.2@sha256:5bcb28b8d0edc4091125f8218f9d088f998bdfc843f8fbb9a4beb4dcd8625684 /usr/bin/composer /usr/local/bin/composer

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
