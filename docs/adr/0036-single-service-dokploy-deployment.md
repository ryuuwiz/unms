---
status: accepted (supersedes ADR-0001, ADR-0034)
---

# Single-Container, Single-Service Dokploy Deployment (Caddy + PHP-FPM + Horizon + Scheduler)

We consolidated the production Dokploy deployment into **one image and one Dokploy service**, running Caddy, PHP-FPM, `php artisan horizon`, and the `schedule:run` polling loop together under a single `supervisord` process tree in the container. This reverses both ADR-0001 (decoupled `app`/`horizon`/`scheduler` containers behind FrankenPHP) and ADR-0034 (no-`supervisord`, native Docker process supervision + `supercronic`), which are marked superseded rather than deleted.

## Context

ADR-0001 and ADR-0034 assumed a 3-container topology to isolate HTTP traffic from MikroTik reconciliation load and to avoid double process supervision. In practice, running 3 decoupled containers on Dokploy means 3 billed/managed services for what is operationally one deployable unit, with no separate scaling need in practice (MySQL, Redis, RustFS, and WAHA already run as their own external Dokploy-managed services outside this image). The added Dokploy service count and deploy coordination cost were not paying for themselves.

## Decision

- One image, one Dokploy service, one `supervisord` tree supervising Caddy, PHP-FPM, Horizon, and the schedule loop (`docker/supervisor.d/*.conf`).
- Runs at 2+ replicas. Safety for replication is handled at the application level, not by adding containers:
  - Every scheduled command in `routes/console.php` already uses `->onOneServer()` (Redis-backed distributed lock), confirmed working in production logs ("Skipping ... because the command already ran on another server").
  - `entrypoint.sh` no longer runs `migrate --force` directly; it calls `php artisan app:migrate-once`, which wraps the migration in a `Cache::lock()` so concurrent replica boots during a rolling deploy don't race each other against the same database.
  - Horizon itself is safe to run multiply — multiple masters coordinating over the same Redis-backed queues is a supported pattern.
- `docker/supervisor.d/php-fpm.conf` sets `stopsignal=QUIT` (PHP-FPM's actual graceful-shutdown signal; the previous default `SIGTERM` killed in-flight requests immediately) with `stopwaitsecs=300` matching `max_execution_time`.
- `HEALTHCHECK` in the Dockerfile stays HTTP-only (`curl /up`) — it proves Caddy+PHP-FPM are alive but deliberately says nothing about Horizon, since Docker `HEALTHCHECK` isn't a good fit for probing a second process in the same container. A separate scheduled command, `horizon:monitor-health`, alerts `super_admin`/`noc` if Horizon is inactive, paused, or has completed no job in 15 minutes.
- `LOG_CHANNEL=stderr` in the production env template so Laravel's own logs surface in `docker logs`/Dokploy's log viewer instead of writing to the container's ephemeral `storage/logs/laravel.log`.

## Consequences

- One Dokploy service to deploy, monitor, and pay for, instead of three.
- A Horizon crash-loop or a slow MikroTik reconciliation job can still, in principle, contend for CPU/memory with the web process in the same container — this is the risk ADR-0001 originally flagged and it is accepted here as a deliberate trade-off for operational simplicity, not resolved.
- Any future need to scale Horizon workers independently of HTTP traffic (or vice versa) requires revisiting this decision — it cannot be done by tuning replica count alone, since replicas scale the whole container (Caddy + PHP-FPM + Horizon + scheduler) as one unit.
- `docs/docker-deployment-guide.md`, which described the old decoupled `app`/`horizon`/`scheduler`/`docker-compose.yml` topology, has been corrected to match this model.
