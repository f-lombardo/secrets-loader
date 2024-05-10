ARG COMPOSER_VERSION=2
ARG PHP_VERSION=8.1

FROM composer:$COMPOSER_VERSION AS composer
FROM php:${PHP_VERSION}-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    && apt-get clean

# Install Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# install xdebug
RUN pecl install xdebug \
&& docker-php-ext-enable xdebug

COPY php-custom.ini /usr/local/etc/php/conf.d/docker-php-custom.ini
COPY xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

ARG COMPOSE_PROJECT_NAME
WORKDIR /var/${COMPOSE_PROJECT_NAME}
