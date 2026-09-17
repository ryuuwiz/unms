---
paths:
  - docker/entrypoint.sh
  - .ai/rules/docker.md
---

# Docker

## No Dockerfile, entrypoint.sh, or docker/ directory exist in this repo anymore
As of 2026-09-17 the repo went through several deploy-mechanism swaps (Docker → Railpack → Procfile → Railpack → deleted) and none of `Dockerfile`, `docker/`, `compose.prod.yaml`, `railpack.json`, or `deploy/` survived (`git log --diff-filter=D` on those paths). Production now builds via Dokploy's own buildpack detection, and boot-time commands run from Dokploy's dashboard field **"Run a custom command in the container after the application initialized"** — not from any file tracked in git. `docs/docker-deployment-guide.md` still documents the deleted Dockerfile/supervisord/Caddy architecture and is stale beyond just the `entrypoint.sh` path references; treat its architecture section as unverified until someone confirms what Dokploy is actually building from.

Any boot-time step that used to live in `entrypoint.sh` (bucket-policy provisioning, migrations, cache warming, storage permission fixes) needs to be re-added to that Dokploy field explicitly — nothing runs it automatically anymore. `app:ensure-public-media-bucket` (ADR-0038) was one such step and had silently stopped running until this was caught.

## storage/logs must stay writable even though LOG_CHANNEL=stderr
ADR-0036 sets `LOG_CHANNEL=stderr` in production so normal app logs go to `docker logs`/Dokploy instead of `storage/logs/laravel.log`, calling that file "ephemeral." That's true for normal logging, but Laravel's `LogManager` has a hardcoded emergency-logger fallback that ALWAYS writes to `storage_path('logs/laravel.log')` whenever the configured channel itself fails to write -- regardless of `LOG_CHANNEL`. If `storage/logs` isn't writable when that fires, the visible error becomes `UnexpectedValueException: ... could not be opened in append mode`, which can mask the real original error.

**This is currently unguarded.** The old `docker/entrypoint.sh` re-applied `chown`/`chmod` on `storage/` on every container boot to survive a runtime volume mount overriding the image's build-time ownership — that step no longer exists anywhere. If storage is volume-mounted in the current Dokploy setup, add an equivalent `chown -R www-data:www-data storage bootstrap/cache` to the Dokploy post-init command (same field used for `app:ensure-public-media-bucket`).
