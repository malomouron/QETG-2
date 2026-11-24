FROM php:8.2-cli-bookworm
RUN rm -f /etc/apt/preferences.d/no-debian-php \
 && apt-get update -qq \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php8.2-cli php8.2-xdebug php8.2-mysql php8.2-xml php8.2-mbstring \
 && rm -rf /var/lib/apt/lists/*
