# ═══════════════════════════════════════════════════════════
# Stage 1: Build Vue 3 Frontend
# ═══════════════════════════════════════════════════════════
FROM node:20-alpine AS frontend-builder
WORKDIR /app

COPY web/package*.json ./
RUN npm ci --ignore-scripts

COPY web/ ./

# Vite reads these at BUILD time — bake them into the JS bundle
ARG VITE_API_URL=/api
ARG VITE_APP_NAME="BoldMark PMS"
ARG VITE_PUSHER_APP_KEY
ARG VITE_PUSHER_APP_CLUSTER=eu

ENV VITE_API_URL=$VITE_API_URL \
    VITE_APP_NAME=$VITE_APP_NAME \
    VITE_PUSHER_APP_KEY=$VITE_PUSHER_APP_KEY \
    VITE_PUSHER_APP_CLUSTER=$VITE_PUSHER_APP_CLUSTER

RUN npm run build

# ═══════════════════════════════════════════════════════════
# Stage 2: Install PHP Production Dependencies
# ═══════════════════════════════════════════════════════════
FROM composer:2 AS composer-builder
WORKDIR /app

# Install dependencies first (better layer caching)
COPY api/composer.json api/composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy full application code then regenerate autoloader
COPY api/ ./
RUN composer dump-autoload --optimize --no-dev

# ═══════════════════════════════════════════════════════════
# Stage 3: Production PHP-FPM Image
# ═══════════════════════════════════════════════════════════
FROM php:8.3-fpm-alpine AS production

# System dependencies
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    libxml2-dev \
    oniguruma-dev \
    curl \
    bash \
    mysql-client

# Compile & install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        xml \
        soap

# Redis PHP extension (required for Horizon + cache + queue)
RUN apk add --no-cache --virtual .build-deps autoconf gcc g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

# Copy Laravel application with vendor/ from composer stage
COPY --from=composer-builder --chown=www-data:www-data /app ./

# Copy built Vue SPA (used by Dockerfile.nginx; kept here for reference/fallback)
COPY --from=frontend-builder --chown=www-data:www-data /app/dist ./public-web

# PHP runtime config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Ensure writable directories exist
RUN mkdir -p \
        storage/logs \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/passport \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/scripts/entrypoint.sh /entrypoint.sh
COPY docker/scripts/healthcheck.sh /healthcheck.sh
RUN chmod +x /entrypoint.sh /healthcheck.sh

EXPOSE 9000

HEALTHCHECK --interval=10s --timeout=5s --retries=3 --start-period=30s \
    CMD /healthcheck.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
