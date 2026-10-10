FROM php:8.5.11-cli@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3 AS base

COPY --from=composer/composer:2.10.3-bin@sha256:206a6dc365fd66aac01e2744d8a6bc898108e802b1bcdde1cd782da788bf1db9 /composer /usr/local/bin/composer

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
