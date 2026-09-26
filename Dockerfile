# ====================================================
# Stage 1: Build Laravel Vite Assets
# ====================================================
FROM node:20-alpine AS assets-builder
WORKDIR /app
COPY package.json ./
RUN npm install --omit=optional
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# ====================================================
# Stage 2: Composer Dependencies
# ====================================================
FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ====================================================
# Stage 3: Production Runtime (PHP 8.2 + Nginx + FFmpeg)
# ====================================================
FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

# Install system dependencies including FFmpeg, GD libs, Nginx, Supervisor
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    nginx \
    supervisor \
    ffmpeg \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev

# Configure and compile PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_sqlite \
        bcmath \
        intl \
        zip \
        opcache \
        pcntl \
        exif \
        gd

# Copy configurations
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy composer binary for artisan/maintenance commands
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application source code
COPY . /var/www/html

# Copy pre-built dependencies and assets
COPY --from=composer-builder /app/vendor /var/www/html/vendor
COPY --from=assets-builder /app/public/build /var/www/html/public/build

# Dump optimized autoload and register packages
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# Setup default directories and permissions
RUN mkdir -p \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/storage/app/public \
        /var/www/html/storage/quran-video-data \
        /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Environment variables
ENV FFMPEG_PATH=ffmpeg \
    FFPROBE_PATH=ffprobe \
    QURAN_STORAGE_PATH=/var/www/html/storage/quran-video-data

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
