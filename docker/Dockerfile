FROM php:8.5.11-cli@sha256:4c529900dda9eefabc8d3b9580ed6510fbb8c6ed3d9741f0d22ef85d568f6fe3 AS base

COPY --from=composer/composer:2.10.3-bin@sha256:696bfbbb82d8ab6ad3672c505bedd659e3815fd1c03cb5ef65ef7ee07a083fa6 /composer /usr/local/bin/composer

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
