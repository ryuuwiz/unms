#!/usr/bin/env bash
# =============================================================================
# GOBILLING — Container entrypoint
#
# Urutan:
#   1. Guard APP_KEY (fail-fast)
#   2. Pastikan .env minimal ada
#   3. Fix ownership storage & bootstrap/cache
#   4. Masuk maintenance mode (php artisan down)
#   5. Wait for DB & Redis (bisa di-skip)
#   6. Migrasi (app:migrate-once, distributed lock)
#   6b. Sinkron role & permission (db:seed RolesAndPermissionsSeeder)
#   7. Bucket policy publik RustFS/S3
#   8. Cache warm-up
#   9. Keluar maintenance mode (php artisan up)
#  10. exec supervisord (jadi PID 1)
#
# Env var opsional:
#   WAIT_FOR_SERVICES=true|false    default true   - skip wait DB+Redis
#   WAIT_TIMEOUT=60                 default 60     - detik tunggu service
#   RUN_MIGRATIONS=true|false       default true   - skip migrate-once
#   RUN_ROLE_SEED=true|false        default true   - skip sinkron role & permission
#   ENABLE_MAINTENANCE=true|false   default true   - skip php artisan down/up
# =============================================================================

set -euo pipefail

cd /var/www/html

# --- Helper logging ke stderr (masuk Docker log viewer) ---------------------
log()  { printf '[entrypoint] %s\n' "$*" >&2; }
warn() { printf '[entrypoint] WARN: %s\n' "$*" >&2; }
fatal(){ printf '[entrypoint] FATAL: %s\n' "$*" >&2; exit 1; }

# =============================================================================
# 1. Guard APP_KEY
# =============================================================================
if [[ -z "${APP_KEY:-}" ]]; then
    warn "Generate a key: php artisan key:generate --show"
    fatal "APP_KEY is not set. Refusing to start."
fi
log "APP_KEY is set."

# =============================================================================
# 2. Pastikan .env ada (kosong; config dari env var container)
# =============================================================================
if [[ ! -f .env ]]; then
    log "Creating empty .env (real config comes from container env vars)."
    : > .env
fi

# =============================================================================
# 3. Fix ownership storage & bootstrap/cache
# =============================================================================
log "Ensuring storage & bootstrap/cache ownership..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# =============================================================================
# 4. Maintenance mode — masuk
#
# WAJIB di sini (sebelum wait-for-services) supaya user tidak menabrak state
# yang belum siap (500 error), tapi dapat 503 maintenance yang jelas.
#
# CATATAN MULTI-REPLICA:
#   php artisan down default-nya pakai driver `file` (per-replica). Untuk
#   2+ replica di Dokploy, set APP_MAINTENANCE_STORE=redis di env production
#   agar flag maintenance dibagi lintas replica. Kalau tidak di-set, tiap
#   replica punya state sendiri — dan request yang mendarat di replica lain
#   tidak akan kena maintenance.
# =============================================================================
MAINTENANCE_ACTIVE=false

if [[ "${ENABLE_MAINTENANCE:-true}" == "true" ]]; then
    log "Entering maintenance mode..."
    if php artisan down --render=errors::503 --retry=30; then
        MAINTENANCE_ACTIVE=true
    else
        warn "Failed to enter maintenance mode (non-fatal). Continuing boot."
    fi
else
    log "Skipping maintenance mode (ENABLE_MAINTENANCE=${ENABLE_MAINTENANCE})."
fi

# =============================================================================
# 5. Tunggu DB & Redis
# =============================================================================
if [[ "${WAIT_FOR_SERVICES:-true}" == "true" ]]; then
    log "Waiting for database & redis (timeout: ${WAIT_TIMEOUT:-60}s)..."
    if ! php artisan app:wait-for-services --timeout="${WAIT_TIMEOUT:-60}"; then
        warn "Services not ready within timeout. Continuing anyway."
    fi
else
    log "Skipping wait-for-services (WAIT_FOR_SERVICES=${WAIT_FOR_SERVICES})."
fi

# =============================================================================
# 6. Migrasi (distributed lock, aman multi-replica)
# =============================================================================
if [[ "${RUN_MIGRATIONS:-true}" == "true" ]]; then
    log "Running migrations (app:migrate-once)..."
    if ! php artisan app:migrate-once --timeout=180; then
        warn "migrate-once failed or timed out. Container will continue booting."
    fi
else
    log "Skipping migrations (RUN_MIGRATIONS=${RUN_MIGRATIONS})."
fi

# =============================================================================
# 6b. Sinkron role & permission
#
# RolesAndPermissionsSeeder memakai syncPermissions(): setiap boot mengembalikan
# permission tiap role bawaan ke definisi di seeder, jadi perubahan manual lewat
# halaman Peran pada role bawaan (super_admin, admin, sales, noc, teknisi,
# customer_service) ikut tertimpa. Role kustom tidak disentuh. --force wajib
# karena APP_ENV=production membuat db:seed meminta konfirmasi interaktif.
# =============================================================================
if [[ "${RUN_ROLE_SEED:-true}" == "true" ]]; then
    log "Syncing roles & permissions (RolesAndPermissionsSeeder)..."
    if ! php artisan db:seed --class=RolesAndPermissionsSeeder --force; then
        warn "Role & permission sync failed (non-fatal). Continuing boot."
    fi
else
    log "Skipping role seed (RUN_ROLE_SEED=${RUN_ROLE_SEED})."
fi

# =============================================================================
# 7. Bucket policy publik RustFS/S3 (ADR-0038)
# =============================================================================
log "Ensuring public media bucket policy..."
if ! php artisan app:ensure-public-media-bucket; then
    warn "Bucket policy setup failed (non-fatal). Continuing."
fi

# =============================================================================
# 8. Cache warm-up (masih di maintenance)
#
# config:cache, route:cache, view:cache, event:cache memakan waktu. Tetap di
# dalam window maintenance supaya user tidak hit cache setengah jadi.
# =============================================================================
log "Warming caches..."
php artisan config:cache  || warn "config:cache failed (non-fatal)."
php artisan route:cache   || warn "route:cache failed (non-fatal)."
php artisan view:cache    || warn "view:cache failed (non-fatal)."
php artisan event:cache   || warn "event:cache failed (non-fatal)."

# artisan di atas jalan sebagai root dan menulis bootstrap/cache/*.php serta
# storage/* milik root; kembalikan ke www-data supaya cache:clear/log runtime
# (php-fpm, horizon) tidak gagal "could not be opened in append mode".
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
php artisan storage:link || true

# =============================================================================
# 9. Keluar maintenance mode — aplikasi siap menerima trafik
# =============================================================================
if [[ "$MAINTENANCE_ACTIVE" == "true" ]]; then
    log "Exiting maintenance mode..."
    if ! php artisan up; then
        warn "Failed to exit maintenance mode (non-fatal). Manual: php artisan up"
    fi
fi

# =============================================================================
# 10. Boot supervisord
#
# `exec` WAJIB: PID 1 menerima SIGTERM dari Docker. Tanpa ini, sinyal
# menyasar bash wrapper, bukan supervisord, sehingga Caddy/Horizon tidak
# graceful shutdown dan container hang sampai Docker kill -9.
# =============================================================================
log "Booting supervisord..."
exec "$@"