FROM php:8.5-cli@sha256:e2b5f2495f2a268082fbb88d8045511bd770390e062b21c35110be78e082d1a3 AS base

COPY --from=composer/composer:2-bin@sha256:cf313f79f608ebab80220796327f341ae663b9fa8065c73c6148c9b67f0b13b3 /composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y unzip libicu-dev  \
    && docker-php-ext-configure intl && docker-php-ext-install intl

WORKDIR /app

FROM base AS development

# TODO: Install xdebug

FROM base AS production

COPY . /app

RUN composer install --no-dev --no-interaction --optimize-autoloader
