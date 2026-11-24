ARG PHP_VERSION=8.2
FROM composer:2 AS php-dependencies
WORKDIR /app
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative

FROM node:22-alpine AS javascript-dependencies
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --no-audit --no-fund

FROM php:${PHP_VERSION}-apache-bookworm AS application
ENV APACHE_DOCUMENT_ROOT=/var/www/html
WORKDIR /var/www/html
RUN rm -f /etc/apt/preferences.d/no-debian-php \
    && apt-get update -qq \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php8.2-xdebug \
    && xdebug_module="$(dpkg -L php8.2-xdebug | grep '/xdebug\.so$' | head -n 1)" \
    && cp "$xdebug_module" "$(php-config --extension-dir)/xdebug.so" \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable xdebug \
    && a2enmod rewrite \
    && mkdir -p uploads/quizzes uploads/avatars /tmp/qetg-coverage \
    && chown -R www-data:www-data uploads /tmp/qetg-coverage
ENV XDEBUG_MODE=coverage
ENV QETG_COVERAGE_DIR=/tmp/qetg-coverage
RUN printf '%s\n' \
    'auto_prepend_file=/var/www/html/tests/.coverage-prepend.php' \
    'auto_append_file=/var/www/html/tests/.coverage-append.php' \
    > /usr/local/etc/php/conf.d/zz-qetg-coverage.ini
COPY . .
COPY --from=php-dependencies /app/vendor ./vendor
COPY --from=javascript-dependencies /app/node_modules ./node_modules
COPY docker/apache-qetg.conf /etc/apache2/conf-available/qetg.conf
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    php_value auto_prepend_file /var/www/html/tests/.coverage-prepend.php' \
    '    php_value auto_append_file /var/www/html/tests/.coverage-append.php' \
    '</Directory>' >> /etc/apache2/conf-available/qetg.conf \
    && a2enconf qetg \
    && mkdir -p uploads/quizzes uploads/avatars /tmp/qetg-coverage \
    && chown -R www-data:www-data uploads /tmp/qetg-coverage
EXPOSE 80
