---
paths:
  - docker/entrypoint.sh
  - .ai/rules/docker.md
---

# Docker

## Dockerfile is back (single container: Caddy + php-fpm + horizon + scheduler under supervisord)
Restored 2026-09-21 from the `Dockerfile` + `docker/` on `feat/dashboard-siklus-tagihan` (`4b61142`, "tested"), replacing the earlier nginx variant from `6a0c8aa`. Main-side additions kept on top: `entrypoint.sh` re-chowns `storage/` + `bootstrap/cache` *after* the cache warm-up (artisan runs as root) and runs `storage:link`; `supervisord.conf` sets `user=root`. Smoke-tested with `compose.smoke.yaml` (gitignored): `/up` 200, `/` 302 -> `/login`, PHP 8.4.

Dokploy must use **Build Type = Dockerfile**, container port **80** (a Railpack build gives 502: no Caddy/Horizon/migrations from this image). `entrypoint.sh` handles `app:wait-for-services`, `app:migrate-once`, `app:ensure-public-media-bucket` and cache warming on every boot, so no Dokploy post-init command is needed. It also runs `php artisan down` (redis maintenance store) during boot — a crash mid-boot leaves the whole fleet in maintenance until `php artisan up`.

## storage/logs must stay writable even though LOG_CHANNEL=stderr
ADR-0036 sets `LOG_CHANNEL=stderr` in production so normal app logs go to `docker logs`/Dokploy instead of `storage/logs/laravel.log`, calling that file "ephemeral." That's true for normal logging, but Laravel's `LogManager` has a hardcoded emergency-logger fallback that ALWAYS writes to `storage_path('logs/laravel.log')` whenever the configured channel itself fails to write -- regardless of `LOG_CHANNEL`. If `storage/logs` isn't writable when that fires, the visible error becomes `UnexpectedValueException: ... could not be opened in append mode`, which can mask the real original error.

`docker/entrypoint.sh` re-applies `chown`/`chmod` on `storage/` and `bootstrap/cache` on every container boot, so a runtime volume mount overriding the image's build-time ownership is covered.
