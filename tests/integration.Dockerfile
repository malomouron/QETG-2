FROM php:8.5-cli

RUN docker-php-ext-install mysqli

WORKDIR /workspace
CMD ["php", "tests/integration.php"]
