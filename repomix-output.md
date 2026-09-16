This file is a merged representation of a subset of the codebase, containing specifically included files, combined into a single document by Repomix.
The content has been processed where comments have been removed, empty lines have been removed, content has been compressed (code blocks are separated by ⋮---- delimiter).

# File Summary

## Purpose
This file contains a packed representation of a subset of the repository's contents that is considered the most important context.
It is designed to be easily consumable by AI systems for analysis, code review,
or other automated processes.

## File Format
The content is organized as follows:
1. This summary section
2. Repository information
3. Directory structure
4. Repository files (if enabled)
5. Multiple file entries, each consisting of:
  a. A header with the file path (## File: path/to/file)
  b. The full contents of the file in a code block

## Usage Guidelines
- This file should be treated as read-only. Any changes should be made to the
  original repository files, not this packed version.
- When processing this file, use the file path to distinguish
  between different files in the repository.
- Be aware that this file may contain sensitive information. Handle it with
  the same level of security as you would the original repository.

## Notes
- Some files may have been excluded based on .gitignore rules and Repomix's configuration
- Binary files are not included in this packed representation. Please refer to the Repository Structure section for a complete list of file paths, including binary files
- Only files matching these patterns are included: docker/*, Dockerfile, .dockerignore
- Files matching patterns in .gitignore are excluded
- Files matching default ignore patterns are excluded
- Code comments have been removed from supported file types
- Empty lines have been removed from all files
- Content has been compressed - code blocks are separated by ⋮---- delimiter
- Files are sorted by Git change count (files with more changes are at the bottom)

# Directory Structure
```
docker/
  entrypoint.sh
  nginx.conf
  php.ini
  supervisord.conf
  www.conf
.dockerignore
Dockerfile
```

# Files

## File: docker/www.conf
```ini
[www]

user = www-data
group = www-data

listen = 127.0.0.1:9000

listen.owner = www-data
listen.group = www-data

pm = dynamic

pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 2
pm.max_spare_servers = 4

pm.max_requests = 500

clear_env = no

catch_workers_output = yes
decorate_workers_output = no

request_terminate_timeout = 60s
```

## File: docker/supervisord.conf
```ini
[supervisord]
nodaemon=true
user=root

logfile=/dev/null
pidfile=/var/run/supervisord.pid
loglevel=info

[program:php-fpm]
command=/usr/local/sbin/php-fpm --nodaemonize

priority=10
autostart=true
autorestart=true
startsecs=1

stopasgroup=true
killasgroup=true

stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0

stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0


[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"

priority=20
autostart=true
autorestart=true
startsecs=1

stopasgroup=true
killasgroup=true

stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0

stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

## File: docker/nginx.conf
```ini
worker_processes auto;

error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    access_log /var/log/nginx/access.log;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;

    keepalive_timeout 65;

    server_tokens off;

    client_max_body_size 420m;

    server {
        listen 80;
        server_name _;

        root /var/www/html/public;
        index index.php;

        location = /up {
            try_files $uri /index.php?$query_string;
        }

        location ^~ /build/assets/ {
            expires 1y;
            add_header Cache-Control "public, immutable";

            try_files $uri =404;
        }

        location ~* ^/flux/(flux|editor)(\.min)?\.(js|css)$ {
            expires off;

            try_files $uri /index.php?$query_string;
        }

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            try_files $uri =404;

            include fastcgi_params;

            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;

            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT $document_root;

            fastcgi_read_timeout 60;
            fastcgi_send_timeout 60;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
}
```

## File: docker/php.ini
```ini
[PHP]

expose_php = Off

memory_limit = 512M

max_execution_time = 60
max_input_time = 60

post_max_size = 420M
upload_max_filesize = 400M

realpath_cache_size = 32M
realpath_cache_ttl = 720

register_argc_argv = Off


[OPcache]

opcache.enable = 1
opcache.enable_cli = 0

opcache.memory_consumption = 512
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 32531

opcache.max_file_size = 0
opcache.validate_timestamps = 0

opcache.save_comments = 1
opcache.use_cwd = 0


[JIT]

opcache.jit = disable
opcache.jit_buffer_size = 0
```

## File: .dockerignore
```
node_modules
.git
.gitignore
*.md
.env*
.DS_Store
Dockerfile
docker-compose*.yml
.dockerignore

# Framework-specific
.next
.nuxt
dist
build
coverage

# The vendor stage's `COPY . .` runs AFTER `composer install --no-dev`.
# Without this, it overwrites that clean --no-dev install with the host's
# own local vendor/ (installed with require-dev packages) -- the resulting
# image then has dev-only classes (pestphp/pest, laravel/pint, etc.)
# physically present but excluded from the --no-dev-filtered autoloader,
# so package:discover's ProviderRepository boot fails with a
# "Class ... not found" error for whichever dev package registers a
# provider (e.g. laramint/laravel-brain).
vendor

# Same leak risk as vendor/ above, for a locally-generated
# bootstrap/cache/packages.php or services.php.
bootstrap/cache/*.php
```

## File: docker/entrypoint.sh
```bash
set -eu
cd /var/www/html
: "${APP_KEY:?FATAL: APP_KEY is not set in the container environment}"
chown -R www-data:www-data \
    storage \
    bootstrap/cache
find storage bootstrap/cache \
    -type d \
    -exec chmod 775 {} \;
find storage bootstrap/cache \
    -type f \
    -exec chmod 664 {} \;
php artisan app:wait-for-services --no-interaction
php artisan app:migrate-once --no-interaction
php artisan optimize:clear --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction
chown -R www-data:www-data \
    storage \
    bootstrap/cache
if [ ! -L public/storage ]; then
    php artisan storage:link --no-interaction
fi
if ! php artisan app:ensure-public-media-bucket --no-interaction; then
    echo "WARNING: Unable to ensure public media bucket." >&2
fi
exec "$@"
```

## File: Dockerfile
```dockerfile
# syntax=docker/dockerfile:1

########################################
# Stage 1: Composer dependencies
########################################
FROM php:8.4-cli-bookworm AS vendor

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        zip \
    && rm -rf /var/lib/apt/lists/*


########################################
# Stage 2: Frontend assets
########################################
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

# Flux CSS is loaded from vendor/livewire/flux
COPY --from=vendor /app/vendor ./vendor

RUN npm run build


########################################
# Stage 3: Production runtime
########################################
FROM php:8.5-fpm-bookworm AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false

WORKDIR /var/www/html


########################################
# Runtime packages
########################################
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libicu72 \
        libpq5 \
        libzip4 \
        libpng16-16 \
        libjpeg62-turbo \
        libfreetype6 \
    && rm -rf /var/lib/apt/lists/*


########################################
# PHP extensions
########################################
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/pear


########################################
# Application
########################################
COPY . .

COPY --from=vendor /app/vendor ./vendor

COPY --from=frontend /app/public/build ./public/build


########################################
# Laravel directories
########################################
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache


########################################
# Laravel package discovery
########################################
RUN php artisan package:discover --ansi


########################################
# Configuration
########################################
COPY docker/nginx.conf \
    /etc/nginx/nginx.conf

COPY docker/supervisord.conf \
    /etc/supervisor/conf.d/supervisord.conf

COPY docker/php.ini \
    /usr/local/etc/php/conf.d/zz-custom.ini

COPY docker/www.conf \
    /usr/local/etc/php-fpm.d/www.conf

COPY docker/entrypoint.sh \
    /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh


########################################
# Validate configuration
########################################
RUN php-fpm -t \
    && nginx -t


########################################
# Container
########################################
EXPOSE 80

HEALTHCHECK \
    --interval=30s \
    --timeout=5s \
    --start-period=15s \
    --retries=3 \
    CMD php -r \
        '$r=@file_get_contents("http://127.0.0.1/up"); exit($r === false ? 1 : 0);'

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-n"]
```
