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
- Only files matching these patterns are included: docker/*, Dockerfile, .dockerignore, compose.prod.yaml
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
.dockerignore
compose.prod.yaml
Dockerfile
```

# Files

## File: docker/php.ini
```ini
[OPcache]
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
```

## File: docker/nginx.conf
```ini
server {
    listen 80;
    server_name _;

    root /var/www/html/public;
    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## File: .dockerignore
```
.git
.gitignore

.env
.env.*
!.env.example

node_modules
vendor

storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*

bootstrap/cache/*.php

Dockerfile
compose*.yaml
docker-compose*.yaml

.DS_Store
*.log

coverage
dist
build
```

## File: compose.prod.yaml
```yaml
services:
  nginx:
    image: nginx:1.29-alpine
    restart: unless-stopped
    ports:
      - "${APP_PORT:-8080}:80"
    volumes:
      - app_public:/var/www/html/public:ro
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      app:
        condition: service_started
    networks:
      - app
  app:
    build:
      context: .
      dockerfile: Dockerfile
    restart: unless-stopped
    env_file:
      - .env
    volumes:
      - app_storage:/var/www/html/storage
      - app_public:/var/www/html/public
    networks:
      - app
  queue:
    build:
      context: .
      dockerfile: Dockerfile
    restart: unless-stopped
    stop_grace_period: 60s
    env_file:
      - .env
    command:
      - php
      - artisan
      - queue:work
      - --sleep=3
      - --tries=3
      - --max-time=3600
    volumes:
      - app_storage:/var/www/html/storage
      - app_public:/var/www/html/public
    networks:
      - app
  horizon:
    build:
      context: .
      dockerfile: Dockerfile
    restart: unless-stopped
    stop_grace_period: 60s
    env_file:
      - .env
    command:
      - php
      - artisan
      - horizon
    volumes:
      - app_storage:/var/www/html/storage
      - app_public:/var/www/html/public
    networks:
      - app
  scheduler:
    build:
      context: .
      dockerfile: Dockerfile
    restart: unless-stopped
    env_file:
      - .env
    command:
      - php
      - artisan
      - schedule:work
    volumes:
      - app_storage:/var/www/html/storage
      - app_public:/var/www/html/public
    networks:
      - app
volumes:
  app_storage:
    driver: local
  app_public:
    driver: local
networks:
  app:
    driver: bridge
```

## File: docker/entrypoint.sh
```bash
set -e
php artisan config:cache
if [ "$1" = "php-fpm" ]; then
    php artisan route:cache
    php artisan view:cache
    php artisan migrate --force --isolated
fi
exec "$@"
```

## File: Dockerfile
```dockerfile
# syntax=docker/dockerfile:1

FROM php:8.4-fpm-alpine

# Runtime dependencies
RUN apk add --no-cache \
    freetype \
    libjpeg-turbo \
    libpng \
    libzip \
    postgresql-libs

# Build dependencies
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    linux-headers \
    postgresql-dev

# PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        exif \
        gd \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        sockets \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# Clean build dependencies
RUN apk del .build-deps \
    && rm -rf /var/cache/apk/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

# Copy Application Source
COPY . .

# Composer Autoload
RUN composer dump-autoload \
    --optimize \
    --no-dev \
    --classmap-authoritative \
    --no-scripts

# Permissions
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

USER root
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
```
