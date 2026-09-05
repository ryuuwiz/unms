# -----------------------------------------------------------------------------
# Stage 1: Base PHP-FPM Runtime
# -----------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS base

# Install essential system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    su-exec \
    netcat-openbsd \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    icu-libs \
    supervisor \
    nginx

# Configure Nginx & Supervisor
RUN mkdir -p /run/nginx /var/log/supervisor \
    && sed -i 's/user nginx;/user www-data;/' /etc/nginx/nginx.conf \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

# Install mlocati PHP extension installer script from official image
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

# Install PHP extensions required by Laravel, Horizon, Mikrotik RouterOS, Spatie & Excel
RUN install-php-extensions \
    pdo_mysql \
    redis \
    pcntl \
    bcmath \
    sockets \
    intl \
    gd \
    zip \
    exif

WORKDIR /var/www/html

# Copy global PHP tuning configuration
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/99-custom.ini

# Copy Nginx server configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy Supervisord configuration
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# Copy entrypoint script and ensure executable permissions
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# -----------------------------------------------------------------------------
# Stage 2: Development Runtime
# -----------------------------------------------------------------------------
FROM base AS dev

# Copy Composer binary into development container
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 80 9000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# -----------------------------------------------------------------------------
# Stage 3: Composer Builder (Production Dependencies)
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-scripts

# -----------------------------------------------------------------------------
# Stage 4: Node Builder (Vite 8 & Tailwind v4 Assets)
# -----------------------------------------------------------------------------
FROM node:24-alpine AS node-builder

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY . .
COPY --from=composer-builder /app/vendor ./vendor

RUN npm run build

# -----------------------------------------------------------------------------
# Stage 5: Production Runtime
# -----------------------------------------------------------------------------
FROM base AS prod

# Copy production OPcache settings
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

# Copy full application source code
COPY . /var/www/html

# Copy optimized production vendor dependencies
COPY --from=composer-builder /app/vendor /var/www/html/vendor

# Copy compiled frontend assets
COPY --from=node-builder /app/public/build /var/www/html/public/build

# Set ownership and directory permissions for www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose standard HTTP port
EXPOSE 80

# Health check using Laravel 11/12/13 native /up endpoint
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://127.0.0.1:80/up || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

