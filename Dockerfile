ARG PHP_VERSION=8.5

FROM composer:2 AS php-dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative

FROM node:22-alpine AS javascript-dependencies
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --no-audit --no-fund

FROM php:${PHP_VERSION}-apache AS application
ENV APACHE_DOCUMENT_ROOT=/var/www/html
WORKDIR /var/www/html

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite

COPY . .
COPY --from=php-dependencies /app/vendor ./vendor
COPY --from=javascript-dependencies /app/node_modules ./node_modules
COPY docker/apache-qetg.conf /etc/apache2/conf-available/qetg.conf

RUN a2enconf qetg \
    && mkdir -p quiz_perso image-profil/image-personaliser \
    && chown -R www-data:www-data quiz_perso image-profil/image-personaliser

EXPOSE 80
