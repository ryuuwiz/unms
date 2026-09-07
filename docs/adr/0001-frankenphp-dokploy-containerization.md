# Production Containerization with FrankenPHP and Dokploy

Transitioning from local Laravel Sail to production deployment, we decided to run FrankenPHP (`dunglas/frankenphp:php8.4-alpine`) in standard stateless request mode with decoupled worker (`horizon`) and daemon (`scheduler`) containers orchestrated via Dokploy PaaS (Traefik ingress).

## Considered Options
- **Laravel Sail in Production**: Rejected because Sail is strictly designed for local dev (`php artisan serve`, single-threaded, host bind mounts, dev utilities).
- **Nginx + PHP-FPM Multi-Container**: Solid alternative, but requires maintaining separate Nginx configurations and FastCGI process managers.
- **FrankenPHP Worker Mode (Octane)**: Considered for speed, but rejected for now to prevent memory leaks in Livewire 4 components and third-party singletons.
- **Monolithic Single Container (Supervisord)**: Rejected because high-load MikroTik reconciliation jobs in Horizon could starve or crash HTTP traffic to the customer billing portal.

## Consequences
- Web requests, Horizon queue workers, and scheduled tasks run from the same hardened base image but in isolated containers (`app`, `horizon`, `scheduler`).
- Ingress SSL is delegated to Dokploy's Traefik reverse proxy via `dokploy-network` labels.
- S3 object storage (RustFS), Redis 7, MySQL 8.4, and WhatsApp gateway (WAHA) run inside the Compose stack with persistent volumes.
