# Laravel (with Queue Workers)
Laravel deployments need PHP-FPM, Nginx, and typically a queue worker. We'll use a single container with Supervisor to manage both processes.

```Dockerfile
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pdo_mysql zip opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first (better caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

# Copy application
COPY . .

# Generate autoloader and run scripts
RUN composer dump-autoload --optimize
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy config files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
```

```docker/nginx.conf
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
    keepalive_timeout 65;

    server {
        listen 80;
        server_name _;
        root /var/www/html/public;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~* ^/flux/(flux|editor)(\.min)?\.(js|css)$ {
            expires off;
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
}
```

```docker/supervisord.conf
[supervisord]
nodaemon=true
logfile=/var/log/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=/usr/local/sbin/php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

```env
APP_NAME=MyApp
APP_ENV=production
APP_KEY=base64:your-key-here
APP_DEBUG=false
APP_URL=https://app.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=dokploy-postgres
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=your-password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=dokploy-redis
REDIS_PORT=6379
``

## Dokploy Settings
- Port: 80
- Health Check Path: /up (Laravel 11+) or create a /health route

## Running Migrations
After deployment, run migrations via Dokploy's terminal or SSH:
```sh
docker exec -it <container-id> php artisan migrate --force
```

# Build Caching Tips
Docker layer caching dramatically speeds up rebuilds. The key principle: copy dependency files before source code.
✓ Good Pattern
```Dockerfile
# Dependencies change less often than source
COPY package.json package-lock.json ./
RUN npm ci

# Source changes frequently
COPY . .
RUN npm run build
```
✗ Bad Pattern
```Dockerfile
# Every code change invalidates npm install
COPY . .
RUN npm ci
RUN npm run build
```

## Dokploy Build Cache
Dokploy preserves Docker build cache between deployments by default. If builds are slow:
1. Check your Dockerfile layer order
2. Use .dockerignore to exclude unnecessary files
3. Consider using BuildKit cache mounts for package managers:
BuildKit cache mount
```
RUN --mount=type=cache,target=/root/.npm npm ci
```

## .dockerignore Template
Every project should have one:
```.dockerignore
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
```

## Quick Debugging
### Build fails?
```
# Check build logs in Dokploy dashboard
# Or manually test locally:
docker build -t test-build .
```
### Container crashes on start?
```
# View container logs
docker logs <container-id>

# Shell into a running container
docker exec -it <container-id> /bin/sh
```
App runs but returns 502?
- Check the port in Dokploy matches what your app listens on
- Verify the app binds to 0.0.0.0, not 127.0.0.1
