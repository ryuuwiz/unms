#!/usr/bin/env bash
# Menggantikan core/providers/php/start-container.sh milik Railpack.
# Railpack tidak lagi menjalankan migrate/optimize sendiri karena
# deploy.startCommand di railpack.json menunjuk ke file ini.
set -euo pipefail

cd /app

echo "==> Menyiapkan direktori writable"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "==> Migrasi database (terkunci, aman untuk multi-replica)"
php artisan app:migrate-once

echo "==> Publish aset Livewire/Flux"
php artisan livewire:publish --assets
chown -R www-data:www-data public/vendor

echo "==> Warm cache"
php artisan storage:link || true
php artisan optimize:clear
php artisan optimize

echo "==> Menyalakan supervisord (frankenphp + horizon + scheduler)"
exec supervisord -c /app/deploy/supervisord.conf