FROM php:8.5-cli@sha256:5749d0780f2d0c993a63b4eac18501f74d2ca8735aeb97d7fd7c8efb7b9b7467 AS base

COPY --from=composer/composer:2-bin@sha256:cf313f79f608ebab80220796327f341ae663b9fa8065c73c6148c9b67f0b13b3 /composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y unzip libicu-dev  \
    && docker-php-ext-configure intl && docker-php-ext-install intl

WORKDIR /app

FROM base AS development

# TODO: Install xdebug

FROM base AS production

COPY . /app

RUN composer install --no-dev --no-interaction --optimize-autoloader
