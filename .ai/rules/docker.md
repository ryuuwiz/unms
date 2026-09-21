---
paths:
  - docker/entrypoint.sh
  - .ai/rules/docker.md
---

# Docker

## Dockerfile is back (single container: nginx + php-fpm + horizon + scheduler under supervisord)
Restored 2026-09-21 from `6a0c8aa` (last version verified with a real `docker build` + `docker run`; deleted in `499ea5b`), plus `exif` and a `mkdir` for `storage/framework/*` (the `.dockerignore` strips their contents, so the dirs vanish from the build context), and the `HEALTHCHECK` now hits `/up`. Smoke-tested with `compose.smoke.yaml` (untracked): `/up` 200, `/` 302 -> `/login`.

Dokploy must use **Build Type = Dockerfile**, container port **80**. The Railpack 502 came from Railpack's default PHP provider running instead of this image (no migrations/Horizon, port/start mismatch). `entrypoint.sh` runs `app:wait-for-services`, `app:migrate-once`, cache warming, `storage:link`, `app:ensure-public-media-bucket` and the `chown` on every boot, so the Dokploy post-init command is no longer needed for those. `docs/docker-deployment-guide.md` still describes Caddy; the image uses nginx.

## storage/logs must stay writable even though LOG_CHANNEL=stderr
ADR-0036 sets `LOG_CHANNEL=stderr` in production so normal app logs go to `docker logs`/Dokploy instead of `storage/logs/laravel.log`, calling that file "ephemeral." That's true for normal logging, but Laravel's `LogManager` has a hardcoded emergency-logger fallback that ALWAYS writes to `storage_path('logs/laravel.log')` whenever the configured channel itself fails to write -- regardless of `LOG_CHANNEL`. If `storage/logs` isn't writable when that fires, the visible error becomes `UnexpectedValueException: ... could not be opened in append mode`, which can mask the real original error.

`docker/entrypoint.sh` re-applies `chown`/`chmod` on `storage/` and `bootstrap/cache` on every container boot, so a runtime volume mount overriding the image's build-time ownership is covered.
