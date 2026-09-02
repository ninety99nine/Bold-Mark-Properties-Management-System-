# ──────────────────────────────────────────────────────────────────────────
# Local DEV image for the Laravel API (hot-reload via bind mount).
# Not used in production — production uses the root Dockerfile (php-fpm).
# ──────────────────────────────────────────────────────────────────────────
FROM php:8.4-cli-alpine

# Runtime libs + PHP extensions (mirrors the production image's extension set)
RUN apk add --no-cache \
        bash git unzip icu-dev libzip-dev oniguruma-dev libxml2-dev \
        libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev mariadb-client \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/dev/api-entrypoint.sh /usr/local/bin/api-entrypoint
RUN chmod +x /usr/local/bin/api-entrypoint

WORKDIR /var/www/html
EXPOSE 8000
ENTRYPOINT ["/usr/local/bin/api-entrypoint"]
