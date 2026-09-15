---
paths:
  - docker/entrypoint.sh
---

# Docker

## storage/logs must stay writable even though LOG_CHANNEL=stderr
ADR-0036 sets `LOG_CHANNEL=stderr` in production so normal app logs go to `docker logs`/Dokploy instead of `storage/logs/laravel.log`, calling that file "ephemeral." That's true for normal logging, but Laravel's `LogManager` has a hardcoded emergency-logger fallback that ALWAYS writes to `storage_path('logs/laravel.log')` whenever the configured channel itself fails to write -- regardless of `LOG_CHANNEL`. If `storage/logs` isn't writable when that fires, the visible error becomes `UnexpectedValueException: ... could not be opened in append mode`, which can mask the real original error.

The Dockerfile's `chown -R www-data:www-data storage` only runs at BUILD time -- a volume mounted over `storage/` at runtime (for persistence across deploys) silently overrides that ownership. `docker/entrypoint.sh` now re-applies `chown`/`chmod` on every container boot (it runs as root, before supervisord drops to www-data) specifically to guard against this. Don't remove that step assuming the Dockerfile's build-time chown is sufficient.
