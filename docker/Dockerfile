FROM php:8.5-cli@sha256:f5d2b71350cdc3c2fc807d6679f5bdac2898d7558945105e46308e4659fb37ac AS base

COPY --from=composer/composer:2-bin@sha256:020cd64e22a251eae7a0cf839907119df2055a42237300697d3023d1306c12d8 /composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y unzip libicu-dev  \
    && docker-php-ext-configure intl && docker-php-ext-install intl

WORKDIR /app

FROM base AS development

# TODO: Install xdebug

FROM base AS production

COPY . /app

RUN composer install --no-dev --no-interaction --optimize-autoloader
