# Dockerfile

## PHP_EXT_PACKAGES is unnecessary duplication
can normally be replaced with:
```
RUN install-php-extensions \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache \
    redis \
    sockets
```

## composer install --no-scripts + php artisan ... deserves attention
Composer scripts for Laravel normally include package discovery. By disabling scripts, you need to make sure Laravel's package-discovery step is explicitly performed before relying on package-provided Artisan commands.
```
RUN php artisan package:discover --ansi \
    && php artisan livewire:publish --assets
```

## COPY . . is potentially your biggest cache/security problem
That can cause:
- huge build contexts
- poor cache reuse
- accidental secret inclusion
- unnecessary files in the final image
You should have a strong .dockerignore for laravel.

## Node builder should use bookworm-slim
```
FROM node:24-bookworm-slim
```

## Pin important external images
At minimum I recommend:
```
composer:2.10.3
caddy:2.11.4-alpine
```

## Another problem: your permission command
Prefer:
```
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```
Then:
```
chown -R www-data:www-data storage bootstrap/cache
```
That's cleaner.

I would also change your build caching slightly

Your current Composer stage has:
```
COPY composer.json composer.lock ./
COPY database/ database/
```
I would remove:
```
COPY database/ database/
```
from the dependency layer.

A migration changing should not cause:
```
composer install
```
to execute again.

So the ideal dependency cache chain is:
```
composer.json
composer.lock
        │
        ▼
composer install
        │
        ▼
vendor/
```
Then application files come later.

Docker's cache model explicitly means that changes to a COPY instruction invalidate downstream instructions, so this ordering matters.

## The frontend stage can potentially be improved further
Currently:
```
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build
```
This means virtually any application source change invalidates that layer.

For Laravel/Vite, that's often acceptable because application resources themselves are part of the build.

But your .dockerignore becomes extremely important.

Also, if your Vite configuration does not actually depend on vendor/, remove:
```
COPY --from=vendor /app/vendor ./vendor
```
That can save a significant amount of work.

## Don't bake Laravel config cache into this image
because your container receives environment variables from Dokploy/runtime.
Instead, do runtime optimization in entrypoint.sh, after environment variables have been injected.
For example:
```
#!/usr/bin/env bash

set -e

cd /var/www/html

php artisan optimize

exec "$@"
```
Laravel's deployment optimization commands are intended to be part of deployment rather than something that should necessarily be frozen into a generic image. The important principle is that runtime-specific environment configuration should remain runtime-specific.

## Your entrypoint.sh should be doing more than just starting Supervisor
For your architecture, I would use something along these lines:
```
#!/usr/bin/env bash

set -e

cd /var/www/html

echo "Running Laravel deployment tasks..."

php artisan optimize

exec "$@"
```
Whether you also add:
```
php artisan migrate --force
```
is a separate deployment policy decision.

I generally would not automatically migrate on every container start when using Dokploy/rolling deployments unless you explicitly want that behavior.

## Caddy image selection
Your current:
```
COPY --from=caddy:2-alpine /usr/bin/caddy /usr/local/bin/caddy
```
is reasonable, and the official Caddy image provides Alpine variants.

But I would make the version explicit:
```
COPY --from=caddy:2.11.4-alpine \
    /usr/bin/caddy \
    /usr/local/bin/caddy
```
The official image currently lists 2.11.4-alpine as a supported tag.

Your validation step is a very good practice:
```
RUN caddy validate ...
```
I would absolutely keep that.

