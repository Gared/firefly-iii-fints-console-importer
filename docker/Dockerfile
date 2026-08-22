FROM php:8.5-cli@sha256:54d82ff9be6bd198145e90c917fc9b2e24230b42e52def8deb3554baf61c451a AS base

COPY --from=composer/composer:2-bin@sha256:020cd64e22a251eae7a0cf839907119df2055a42237300697d3023d1306c12d8 /composer /usr/local/bin/composer

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
