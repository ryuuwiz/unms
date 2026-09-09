# -----------------------------------------------------------------------------
# Stage 1: Base Runtime Environment with FrankenPHP
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:php8.4-alpine AS base

# Install system dependencies and build utilities
RUN apk add --no-cache \
    bash \
    curl \
    netcat-openbsd \
    libcap

# Install Supercronic for reliable containerized cron scheduling
RUN set -ex; \
    ARCH=$(uname -m); \
    case "$ARCH" in \
        x86_64) SUPERCRONIC_ARCH="amd64"; SUPERCRONIC_SHA1="71b0d58cc53f6bd72cf2f293e09e294b79c666d8" ;; \
        aarch64) SUPERCRONIC_ARCH="arm64"; SUPERCRONIC_SHA1="e0f0c06ebc5627e43b25475711e694450489ab00" ;; \
        *) echo "Unsupported architecture: $ARCH" >&2; exit 1 ;; \
    esac; \
    SUPERCRONIC_URL="https://github.com/aptible/supercronic/releases/download/v0.2.33/supercronic-linux-${SUPERCRONIC_ARCH}"; \
    curl -fsSL "$SUPERCRONIC_URL" -o /usr/local/bin/supercronic; \
    echo "${SUPERCRONIC_SHA1}  /usr/local/bin/supercronic" | sha1sum -c -; \
    chmod +x /usr/local/bin/supercronic

# Install required PHP extensions for Laravel, Livewire, DomPDF, and Mikrotik RouterOS API
RUN install-php-extensions \
    pdo_mysql \
    gd \
    zip \
    pcntl \
    sockets \
    exif \
    bcmath \
    intl \
    opcache \
    redis

# Configure production PHP and OPcache settings
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.revalidate_freq=0"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.save_comments=1"; \
    echo "opcache.jit=tracing"; \
    echo "opcache.jit_buffer_size=64M"; \
} > /usr/local/etc/php/conf.d/docker-php-ext-opcache-custom.ini && \
{ \
    echo "memory_limit=512M"; \
    echo "upload_max_filesize=64M"; \
    echo "post_max_size=64M"; \
    echo "max_execution_time=300"; \
    echo "date.timezone=Asia/Jakarta"; \
} > /usr/local/etc/php/conf.d/docker-php-custom.ini

# Set working directory
WORKDIR /var/www/html

# -----------------------------------------------------------------------------
# Stage 2: Composer Dependency Builder
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-builder

WORKDIR /app

# Copy dependency manifests
COPY composer.json composer.lock ./

# Install production dependencies without running scripts
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

# Copy application source for autoload classmap generation
COPY app/ app/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY database/ database/
COPY lang/ lang/
COPY routes/ routes/
COPY artisan artisan

# Generate optimized production classmap
RUN composer dump-autoload --optimize --no-dev --no-scripts

# -----------------------------------------------------------------------------
# Stage 3: Node & Vite Asset Builder
# -----------------------------------------------------------------------------
FROM node:22-bookworm-slim AS node-builder

WORKDIR /app

# Copy NPM package manifests
COPY package*.json ./

# Install npm dependencies
RUN npm ci

# Copy composer vendor so Tailwind v4 can resolve Flux UI css and stubs
COPY --from=composer-builder /app/vendor ./vendor

# Copy views, templates, and config required by Tailwind v4 / Vite scanner
COPY resources/ resources/
COPY app/ app/
COPY public/ public/
COPY vite.config.js ./

# Build production frontend bundle
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 4: Production Image Assembly
# -----------------------------------------------------------------------------
FROM base AS production

WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy optimized composer vendor dependencies
COPY --from=composer-builder /app/vendor /var/www/html/vendor

# Copy compiled frontend assets
COPY --from=node-builder /app/public/build /var/www/html/public/build

# Copy production Caddyfile, crontab, and Entrypoint script
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/crontab /etc/crontabs/laravel-cron
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Set directory permissions and executable flags
RUN chmod +x /usr/local/bin/entrypoint.sh && \
    rm -f bootstrap/cache/*.php && \
    mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache && \
    chown -R www-data:www-data /var/www/html

# Healthcheck for FrankenPHP
HEALTHCHECK --interval=15s --timeout=5s --start-period=60s --retries=5 \
    CMD curl -f http://127.0.0.1/up || exit 1

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
