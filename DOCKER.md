# Docker Deployment & Operation Guide

This guide details how to run, manage, and deploy the **GOBILLING / UNMS** application using Docker.

---

## 1. Quick Start: Local Development

### Step 1: Prepare Environment Configuration
Copy the Docker environment template:
```bash
cp .env.docker.example .env
```
Generate an application encryption key if you haven't already:
```bash
php artisan key:generate
```
*(Or generate it inside the container after starting in Step 3)*.

### Step 2: Build & Start the Containers
Start all services in detached mode:
```bash
docker compose up -d --build
```

### Step 3: Initialize Database & Superadmin
Run the installation wizard inside the application container:
```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan app:install
```
Follow the interactive prompt to set up the default superadmin user, database tables, and seeders.

---

## 2. Port & Service Access Matrix (Local Dev)

| Service | Address | Default Port | Description |
| :--- | :--- | :--- | :--- |
| **Web Application** | `http://localhost:8000` | 8000 | GOBILLING web interface & customer portal |
| **Horizon Dashboard** | `http://localhost:8000/horizon` | 8000 | Real-time queue metrics and job workers |
| **Mailpit Web UI** | `http://localhost:8025` | 8025 | Local email inbox catcher (SMTP port 1025) |
| **MySQL Database** | `localhost:3306` | 3306 | User: `gobilling`, Password: `secret` |
| **Redis** | `localhost:6379` | 6379 | Redis cache, session, and queue broker |

---

## 3. Daily Operations & CLI Commands

### Opening a Shell
Open an interactive shell inside the application container:
```bash
# As unprivileged www-data user:
docker compose exec -u www-data app bash

# As root (for system debugging):
docker compose exec -u 0 app bash
```

### Viewing Container Logs & Process Status
```bash
# Tail all container logs:
docker compose logs -f app

# Inspect supervisor sub-processes (nginx, php-fpm, horizon, scheduler):
docker compose exec app supervisorctl status

# Restart an individual process without restarting the container:
docker compose exec app supervisorctl restart horizon
```

### Database Migrations & Seeds
```bash
# Run new migrations:
docker compose exec app php artisan migrate

# Check migration status:
docker compose exec app php artisan migrate:status

# Seed database:
docker compose exec app php artisan db:seed
```

### Restarting Horizon Workers
When you make modifications to Job classes or listeners, instruct Horizon to terminate gracefully and reload:
```bash
docker compose exec app php artisan horizon:terminate
```

### Running Tests
Execute the Pest test suite inside the container:
```bash
docker compose exec app php artisan test --compact
```

---

## 4. Production Deployment

The production configuration uses `docker-compose.prod.yml`, which features:
* **Multi-stage optimized production image:** No dev dependencies, OPcache enabled with `validate_timestamps=0`, pre-compiled Vite 8 & Tailwind v4 assets.
* **Network Isolation:** MySQL and Redis ports are not exposed to the host machine.
* **Reverse Proxy Friendly:** Nginx binds to `127.0.0.1:${APP_PORT:-8080}` to receive traffic from Traefik, Cloudflare Tunnel, or Host Nginx.
* **Persistent Uploads:** The `storage/` directory is mapped to a dedicated named volume (`app_storage`).

### Production Deployment Steps

1. Configure `.env` on your production server:
   ```bash
   cp .env.docker.example .env
   # Set APP_ENV=production, APP_DEBUG=false, strong DB_PASSWORD and DB_ROOT_PASSWORD
   ```

2. Build and start the production stack:
   ```bash
   docker compose -f docker-compose.prod.yml up -d --build
   ```

3. Run production migrations:
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
   ```

4. First-time installation:
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan app:install --force
   ```

5. Zero-downtime update script pattern:
   ```bash
   git pull origin main
   docker compose -f docker-compose.prod.yml build
   docker compose -f docker-compose.prod.yml up -d --no-deps app
   docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
   docker compose -f docker-compose.prod.yml exec app php artisan horizon:terminate
   docker image prune -f
   ```

---

## 5. Architectural Pattern: Unified Application Container (Supervisord)

The `app` container is built with a unified multi-process runtime using **Supervisord**:
* **Nginx:** Handles HTTP termination, Livewire/Flux static asset streaming, and FastCGI proxy to PHP-FPM on `127.0.0.1:9000`.
* **PHP-FPM:** Executes Laravel application code with master process privilege separation.
* **Laravel Horizon:** Manages Redis queue workers and supervisors with `user: www-data`.
* **Laravel Task Scheduler:** Runs `php artisan schedule:work` with `user: www-data`.

**Advantages of the Unified Container Model:**
1. **Simplified Topology:** Only 4 containers to manage (`app`, `redis`, `mysql`, `mailpit`), reducing memory overhead and container clutter.
2. **Co-located Static Assets:** Nginx serves `/var/www/html/public` assets directly from the same filesystem without shared volume synchronization overhead.
3. **Controlled Signal Propagation:** Supervisord receives `SIGTERM` on container shutdown, gracefully telling Horizon, Scheduler, PHP-FPM, and Nginx to drain and terminate.
4. **Independent Process Control:** Any individual process can be inspected or restarted on the fly via `docker compose exec app supervisorctl restart <program>`.

---

## 6. Troubleshooting

### MikroTik RouterOS Socket Connection Fails
* **Symptom:** `RouterOsClientException: Connection refused or timed out`.
* **Verification:** Test TCP connectivity from inside the container:
  ```bash
  docker compose exec app nc -zv <ROUTER_IP> 8728
  ```
* **Solution:** Ensure the Docker host has routing access to your MikroTik management subnet, and check MikroTik `/ip service` to confirm `api` (port 8728) is enabled.

### Storage Permissions (`Permission Denied`)
The `docker/entrypoint.sh` script automatically verifies and sets `chown -R www-data:www-data storage bootstrap/cache` on container boot. If host filesystem permission conflicts occur:
```bash
docker compose exec -u 0 app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```
