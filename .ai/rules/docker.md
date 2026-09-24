---
paths:
  - docker/entrypoint.sh
  - .ai/rules/docker.md
---

# Docker

## Dockerfile is back (single container: Caddy + php-fpm + horizon + scheduler under supervisord)
Restored 2026-09-21 from the `Dockerfile` + `docker/` on `feat/dashboard-siklus-tagihan` (`4b61142`, "tested"), replacing the earlier nginx variant from `6a0c8aa`. Main-side additions kept on top: `entrypoint.sh` re-chowns `storage/` + `bootstrap/cache` *after* the cache warm-up (artisan runs as root) and runs `storage:link`; `supervisord.conf` sets `user=root`. Smoke-tested with `compose.smoke.yaml` (gitignored): `/up` 200, `/` 302 -> `/login`, PHP 8.4.

Dokploy must use **Build Type = Dockerfile**, container port **80** (a Railpack build gives 502: no Caddy/Horizon/migrations from this image). `entrypoint.sh` handles `app:wait-for-services`, `app:migrate-once`, `db:seed --class=RolesAndPermissionsSeeder --force` (`RUN_ROLE_SEED=false` skips it), `app:ensure-public-media-bucket` and cache warming on every boot, so no Dokploy post-init command is needed. It also runs `php artisan down` (redis maintenance store) during boot — a crash mid-boot leaves the whole fleet in maintenance until `php artisan up`.

## storage/logs must stay writable even though LOG_CHANNEL=stderr
ADR-0036 sets `LOG_CHANNEL=stderr` in production so normal app logs go to `docker logs`/Dokploy instead of `storage/logs/laravel.log`, calling that file "ephemeral." That's true for normal logging, but Laravel's `LogManager` has a hardcoded emergency-logger fallback that ALWAYS writes to `storage_path('logs/laravel.log')` whenever the configured channel itself fails to write -- regardless of `LOG_CHANNEL`. If `storage/logs` isn't writable when that fires, the visible error becomes `UnexpectedValueException: ... could not be opened in append mode`, which can mask the real original error.

`docker/entrypoint.sh` re-applies `chown`/`chmod` on `storage/` and `bootstrap/cache` on every container boot, so a runtime volume mount overriding the image's build-time ownership is covered.

## RolesAndPermissionsSeeder runs on every boot and resets the built-in roles
The seeder uses `syncPermissions()`, so each boot overwrites the permissions of `super_admin`, `admin`, `sales`, `noc`, `teknisi`, `customer_service` with the seeder's definitions. Changes made to those roles in the Roles UI are lost on the next deploy/restart; put permanent changes in `database/seeders/RolesAndPermissionsSeeder.php`. Custom roles are untouched. `--force` is required (production `db:seed` otherwise prompts and aborts without a TTY). Not lock-guarded like `app:migrate-once`, so replicas booting together may race on `firstOrCreate` (failure is non-fatal).
