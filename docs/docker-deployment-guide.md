# GOBILLING / UNMS — Docker & Deployment Guide

Dokumentasi resmi panduan kontainerisasi Docker, deployment lokal, deployment produksi mandiri (VPS), dan deployment otomatis berbasis **Dokploy** untuk sistem GOBILLING (ISP Billing & Network Management System).

---

## Daftar Isi
1. [Arsitektur & Topologi Kontainer](#1-arsitektur--topologi-kontainer)
2. [File Konfigurasi & Struktur Direktori](#2-file-konfigurasi--struktur-direktori)
3. [Panduan Local Development](#3-panduan-local-development)
4. [Panduan Deployment di Dokploy](#4-panduan-deployment-di-dokploy)
5. [Panduan Deployment VPS Standalone](#5-panduan-deployment-vps-standalone)
6. [Konfigurasi Environment Variable](#6-konfigurasi-environment-variable)
7. [Operasional & Pemeliharaan (Maintenance)](#7-operasional--pemeliharaan-maintenance)
8. [Troubleshooting & FAQ](#8-troubleshooting--faq)

---

## 1. Arsitektur & Topologi Kontainer

GOBILLING dibangun dengan arsitektur modular yang memisahkan traffic HTTP, antrean latar belakang (Queue Worker), penjadwalan berkala (Scheduler), dan gateway eksternal ke dalam kontainer terisolasi:

```
                          [ Internet Traffic / Users ]
                                       │
                                       ▼
                     [ Ingress / Traefik (Dokploy) / Nginx ]
                                       │ Port 80
                                       ▼
                   ┌────────────────────────────────────────┐
                   │          gobilling_nginx               │
                   │    (Alpine Nginx Web Server)           │
                   └───────────────────┬────────────────────┘
                                       │ FastCGI (Port 9000)
                                       ▼
                   ┌────────────────────────────────────────┐
                   │           gobilling_app                │
                   │   (PHP 8.3 FPM / Laravel Engine)       │
                   └───────┬───────────────────┬────────────┘
                           │                   │
         ┌─────────────────┼───────────────────┼─────────────────┐
         │                 │                   │                 │
         ▼                 ▼                   ▼                 ▼
┌─────────────────┐ ┌─────────────┐ ┌────────────────────┐ ┌───────────┐
│gobilling_horizon│ │  scheduler  │ │  gobilling_mysql   │ │   redis   │
│(Laravel Horizon)│ │ (Task Cron) │ │    (MySQL 8.4)     │ │ (Redis 7) │
└────────┬────────┘ └─────────────┘ └────────────────────┘ └───────────┘
         │
         ├─── Outbound TCP 8728/8729 ──► [ Perangkat MikroTik RouterOS ]
         │
         ▼ HTTP (Port 3000)
┌─────────────────┐
│ gobilling_waha  │ ──► [ WhatsApp Web Engine / Gateway ]
│ (WAHA Sessions) │
└─────────────────┘
```

### Komponen Layanan:
- **`app`**: Runtime PHP 8.3-FPM yang mengeksekusi request backend Laravel, Livewire 4, dan modul API.
- **`nginx`**: Web server yang menangani static assets, buffer Livewire, limit upload 64M, dan proxy FastCGI.
- **`horizon`**: Daemon worker `php artisan horizon` dengan 2 supervisor terisolasi:
  - `supervisor-high`: Khusus antrean prioritas instan `mikrotik-high` (provisi akun, isolir, buka isolir).
  - `supervisor-low`: Auto-scaling worker untuk `mikrotik-low`, `default`, dan blast notifikasi `wa-blast`.
- **`scheduler`**: Kontainer daemon scheduler `php artisan schedule:work` yang mengeksekusi rekonsiliasi MikroTik harian, pembuatan tagihan otomatis, cek isolir jatuh tempo, dan pengecekan kedaluwarsa VA Xendit.
- **`mysql`**: Database MySQL 8.4 LTS dengan dukungan query geospasial GIS (`ST_Distance_Sphere` untuk Estimasi Kabel & ODP).
- **`redis`**: Broker in-memory Redis 7 Alpine untuk queue Horizon, cache aplikasi, atomic lock `onOneServer()`, dan sesi.
- **`waha`**: WhatsApp HTTP API (Core) untuk pairing sesi staf dan transmisi notifikasi tagihan real-time.
- **`vite`** *(Hanya Dev)*: Node 22 runtime dengan Vite HMR di port `5173`.
- **`mailpit`** *(Hanya Dev)*: Mock SMTP catcher di port `1025` dengan Web Dashboard di port `8025`.

---

## 2. File Konfigurasi & Struktur Direktori

```text
unms/
├── docker/
│   ├── Dockerfile                  # Multi-stage: base -> dev -> composer-builder -> node-builder -> prod
│   ├── entrypoint.sh               # Entrypoint cerdas (wait DB/Redis, izin volume, cache, auto-migrate)
│   ├── nginx/
│   │   ├── Dockerfile              # Image Nginx mandiri (self-contained) untuk produksi
│   │   ├── nginx.conf              # Buffering FastCGI (32k/16k), gzip, upload limit 64M
│   │   └── default.conf            # Server block, Livewire routing, asset caching
│   ├── php/
│   │   ├── php.ini                 # memory_limit 512M, max_execution_time 300s, upload 64M
│   │   ├── opcache.ini             # OPcache produksi & JIT tracing buffer 100M
│   │   └── www.conf                # Pool PHP-FPM dynamic (50 worker, clear_env=no)
│   └── mysql/
│       └── my.cnf                  # Konfigurasi MySQL 8.4 utf8mb4 & tuning InnoDB
├── docker-compose.yml              # Stack Produksi Dokploy (dengan label Traefik & dokploy-network)
├── .env.docker.example             # Template variabel environment siap pakai untuk Docker
└── .dockerignore                   # Optimasi build context (mengabaikan node_modules, vendor, dll.)
```

---

## 3. Panduan Local Development

### Langkah 1: Siapkan File Environment
Salin template konfigurasi lokal Docker:
```bash
cp .env.docker.example .env
```

### Langkah 2: Jalankan Docker Compose
Jalankan seluruh stack pengembangan (App, Nginx, Horizon, Scheduler, Vite, MySQL, Redis, Mailpit, WAHA):
```bash
docker compose up -d --build
```

### Langkah 3: Inisialisasi Database & Superadmin Awal
Pada instalasi pertama kali, jalankan perintah setup wizard GOBILLING di dalam kontainer `app`:
```bash
# Generate application key
docker compose exec app php artisan key:generate

# Jalankan installer produksi interaktif (migrasi, storage link, master data seeder, akun superadmin)
docker compose exec app php artisan app:install
```

### Port & Akses Layanan Lokal:
| Layanan | URL / Port | Keterangan |
| :--- | :--- | :--- |
| **Aplikasi Web** | [http://localhost:8000](http://localhost:8000) | Antarmuka backoffice GOBILLING & Portal Pelanggan |
| **Vite HMR** | [http://localhost:5173](http://localhost:5173) | Hot Module Replacement (Tailwind v4 / JS / Livewire) |
| **Mailpit Dashboard** | [http://localhost:8025](http://localhost:8025) | Tinjauan email pengujian (SMTP port 1025) |
| **WAHA API / Dashboard** | [http://localhost:3000](http://localhost:3000) | Dashboard sesi pairing WhatsApp |
| **Horizon Dashboard** | [http://localhost:8000/horizon](http://localhost:8000/horizon) | Metrik antrean worker real-time |
| **Database MySQL** | `localhost:3306` | User: `gobilling`, Pass: `secret`, DB: `gobilling` |
| **Redis Cache** | `localhost:6379` | Host: `redis`, Port: `6379` |

### Perintah Berguna Saat Development:
```bash
# Melihat log semua kontainer
docker compose logs -f

# Melihat log khusus Horizon queue worker
docker compose logs -f horizon

# Masuk ke shell bash kontainer PHP
docker compose exec app sh

# Menjalankan unit & feature test Pest
docker compose exec app php artisan test --compact

# Menjalankan migrasi database baru
docker compose exec app php artisan migrate

# Format kode sesuai Laravel Pint
docker compose exec app vendor/bin/pint --format agent

# Restart antrean Horizon setelah mengubah Job
docker compose exec app php artisan horizon:terminate
```

---

## 4. Panduan Deployment di Dokploy

Dokploy adalah panel PaaS open-source berbasis Docker dan Traefik. Repositori ini telah dikonfigurasi dengan file tunggal `docker-compose.yml` yang terintegrasi secara native dengan jaringan Traefik Dokploy tanpa perlu pengaturan khusus.

### Langkah 1: Buat Layanan Baru di Dokploy
1. Masuk ke dashboard **Dokploy**.
2. Pilih Project / Environment yang diinginkan.
3. Klik **Create Service** $\rightarrow$ pilih jenis **Compose**.
4. Beri nama layanan (misalnya `gobilling`).

### Langkah 2: Konfigurasi Git & Compose Path
1. Pada tab **General**:
   - **Source**: Pilih **Git**.
   - **Repository**: Masukkan URL repositori Git Anda.
   - **Branch**: Pilih `main` (atau branch rilis produksi).
   - **Compose Path**: Biarkan default (`docker-compose.yml`).

### Langkah 3: Konfigurasi Environment Variables di Dokploy
Masuk ke tab **Environment** pada Dokploy dan masukkan variabel produksi. Dokploy secara otomatis menyimpannya ke file `.env`:

```dotenv
# Identitas Aplikasi
APP_NAME=GOBILLING
APP_ENV=production
APP_KEY=base64:MASUKKAN_APP_KEY_PRODUKSI_ANDA_DISINI
APP_DEBUG=false
APP_URL=https://buroq.gobilling.id

# Domain Traefik Dokploy (SSL Otomatis Let's Encrypt)
APP_DOMAIN=buroq.gobilling.id
DOKPLOY_ROUTER_NAME=gobilling

# Database Internal MySQL (Docker)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=gobilling
DB_USERNAME=gobilling
DB_PASSWORD=GANTI_DENGAN_PASSWORD_DATABASE_KUAT_ACAK

# Redis & Queue Internal (Docker)
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=GANTI_DENGAN_PASSWORD_REDIS_KUAT_ACAK
REDIS_PORT=6379
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
HORIZON_PREFIX=gobilling_horizon:

# Migrasi Otomatis Saat Deploy
RUN_MIGRATIONS=true

# WhatsApp Gateway (WAHA Internal)
WAHA_HOST=http://waha:3000
WAHA_SESSION=gobilling
WAHA_API_KEY=GANTI_DENGAN_TOKEN_WAHA_RAHASIA

# Payment Gateway (Produksi / Sandbox)
XENDIT_SECRET_KEY=xnd_production_xxxxxxxxxxxx
XENDIT_CALLBACK_TOKEN=xxxxxxxxxxxx
XENDIT_ENV=production

# Email Gateway (Postmark / SES / SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@domainanda.com
MAIL_PASSWORD=xxxxxxxxxxxx
MAIL_FROM_ADDRESS="billing@domainanda.com"
MAIL_FROM_NAME="GOBILLING"
```

### Langkah 4: Klik Deploy
Klik tombol **Deploy** di Dokploy:
1. Dokploy akan meng-clone branch yang ditentukan.
2. Membangun image multi-stage (PHP-FPM, Horizon, Scheduler, dan Nginx).
3. Menjalankan seluruh kontainer dan menghubungkan kontainer Nginx ke `dokploy-network`.
4. Skrip entrypoint otomatis membuat `storage:link`, menjalankan `migrate --force`, dan menghangatkan cache (`config:cache`, `route:cache`, `view:cache`).
5. Traefik otomatis menerbitkan sertifikat SSL Let's Encrypt untuk `https://billing.domainanda.com`.

### Langkah 5: Inisialisasi Akun Superadmin Pertama Kali
Buka tab **Terminal** pada kontainer `app` (atau jalankan via SSH host server):
```bash
docker exec -it gobilling_dokploy_app php artisan app:install
```
Ikuti wizard interaktif untuk membuat akun Superadmin dan menyuntikkan template RBAC default.

---

## 5. Panduan Deployment VPS Standalone

Gunakan [`docker-compose.prod.yml`](file:///C:/Ryu/Projects/unms/docker-compose.prod.yml) jika mendeploy pada server VPS biasa (Ubuntu/Debian) menggunakan Nginx host, Cloudflare Tunnel, Traefik eksternal, atau Nginx Proxy Manager sebagai SSL reverse proxy.

### 1. Salin File Environment
```bash
cp .env.docker.example .env
# Edit variabel: nano .env (pastikan APP_ENV=production, APP_DEBUG=false, DB_PASSWORD, dll.)
```

### 2. Build & Jalankan Kontainer
```bash
docker compose -f docker-compose.prod.yml up -d --build
```
Nginx kontainer akan membinding port internal `127.0.0.1:8080` (dapat diubah via `APP_PORT=8080` di `.env`).

### 3. Konfigurasi Nginx di Host Server (Sebagai SSL Reverse Proxy)
Buat file vhost di host `/etc/nginx/sites-available/billing.domainanda.com`:
```nginx
server {
    listen 80;
    server_name billing.domainanda.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name billing.domainanda.com;

    ssl_certificate /etc/letsencrypt/live/billing.domainanda.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/billing.domainanda.com/privkey.pem;

    client_max_body_size 64M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_buffering off;
        proxy_read_timeout 300s;
    }
}
```

### 4. Skrip CI/CD Pembaruan Rilis (Zero-Downtime Rollout)
```bash
#!/bin/bash
set -e

echo "Mengambil commit terbaru..."
git pull origin main

echo "Membangun image baru..."
docker compose -f docker-compose.prod.yml build

echo "Memperbarui kontainer berjalan..."
docker compose -f docker-compose.prod.yml up -d --no-deps app horizon scheduler nginx

echo "Merestart worker Horizon secara graceful..."
docker compose -f docker-compose.prod.yml exec app php artisan horizon:terminate

echo "Pembersihan image lama yang tidak terpakai..."
docker image prune -f

echo "Deploy selesai dengan sukses!"
```

---

## 6. Konfigurasi Environment Variable

Daftar parameter kritis yang wajib diperhatikan saat konfigurasi Docker:

| Variabel | Default Docker | Keterangan |
| :--- | :--- | :--- |
| `APP_ENV` | `production` / `local` | Mode environment Laravel. |
| `APP_DEBUG` | `false` (prod) | Wajib `false` pada lingkungan produksi demi keamanan. |
| `APP_URL` | `https://...` | URL canonical aplikasi (penting untuk link gateway & webhook). |
| `APP_DOMAIN` | `billing.example.com` | Domain router untuk Traefik di Dokploy. |
| `DB_CONNECTION` | `mysql` | Driver database (gunakan `mysql`). |
| `DB_HOST` | `mysql` | Nama hostname kontainer database MySQL di Docker network. |
| `DB_PORT` | `3306` | Port internal MySQL. |
| `REDIS_HOST` | `redis` | Nama hostname kontainer Redis di Docker network. |
| `QUEUE_CONNECTION` | `redis` | Wajib `redis` agar antrean diproses oleh Laravel Horizon. |
| `RUN_MIGRATIONS` | `true` | Otomatis menjalankan `migrate --force` saat kontainer boot. |
| `WAHA_HOST` | `http://waha:3000` | URL internal endpoint engine WhatsApp WAHA. |
| `WAHA_API_KEY` | *(token rahasia)* | Kunci API untuk mengautentikasi request ke WAHA. |
| `WWWUSER` / `WWWGROUP` | `1000` *(hanya dev)* | Menyamakan UID user di host lokal untuk mencegah permission denied. |

---

## 7. Operasional & Pemeliharaan (Maintenance)

### Backup Basis Data MySQL
Jalankan backup database langsung dari luar kontainer:
```bash
# Dokploy:
docker exec gobilling_dokploy_mysql mysqldump -u gobilling -pSECRET gobilling > backup_$(date +%F_%T).sql

# Standalone Prod:
docker compose -f docker-compose.prod.yml exec -T mysql mysqldump -u gobilling -pSECRET gobilling > backup_$(date +%F_%T).sql
```

### Restore Basis Data MySQL
```bash
docker exec -i gobilling_dokploy_mysql mysql -u gobilling -pSECRET gobilling < backup_2026-09-03.sql
```

### Memeriksa Status Horizon
```bash
docker exec -it gobilling_dokploy_app php artisan horizon:status
```

### Pairing WhatsApp Web (WAHA)
1. Buka antarmuka GOBILLING pada menu **Integrasi / Gateway WA**.
2. Klik tombol **Pairing Sesi**.
3. Pindai kode QR yang muncul langsung menggunakan aplikasi WhatsApp staf pengelola.
4. Sesi otomatis tersimpan pada volume permanen `gobilling_prod_waha_sessions`.

---

## 8. Troubleshooting & FAQ

### 1. Masalah Izin Berkas Storage / Logs (`Permission Denied`)
**Penyebab**: Docker named volume di-mount ke folder `/var/www/html/storage` dengan kepemilikan user `root` oleh sistem operasi host.  
**Solusi**: Skrip [`docker/entrypoint.sh`](file:///C:/Ryu/Projects/unms/docker/entrypoint.sh) telah dirancang untuk mendeteksi hak akses saat container start dan otomatis menjalankan `chown -R www-data:www-data /var/www/html/storage` serta menjatuhkan privilege ke `www-data` menggunakan `su-exec`. Jika terjadi perubahan manual, perbaiki dengan perintah:
```bash
docker exec -it -u 0 gobilling_dokploy_app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```

### 2. Horizon Worker Menolak Berhenti / Hang Saat Deploy
**Solusi**: Konfigurasi kontainer kami menyertakan `stop_grace_period: 60s`. Horizon memerlukan waktu untuk menyelesaikan job MikroTik yang sedang aktif sebelum proses terminated. Untuk memicu restart tanpa restart kontainer:
```bash
docker exec -it gobilling_dokploy_app php artisan horizon:terminate
```

### 3. Koneksi API MikroTik Gagal dari Dalam Kontainer
**Penyebab**: Firewall host memblokir traffic keluar TCP port 8728/8729 atau MikroTik berada di segmen IP yang tidak dapat dirutekan.  
**Uji Koneksi**:
```bash
# Uji koneksi ping dan socket dari dalam kontainer app
docker exec -it gobilling_dokploy_app nc -zv IP_ROUTER_MIKROTIK 8728
```
Pastikan IP MikroTik dapat dijangkau oleh server host atau tambahkan VPN/Wireguard tunnel pada server host.

### 4. Error 404 pada Dokploy
**Penyebab**: Kontainer `nginx` belum terhubung ke jaringan eksternal `dokploy-network` atau label Traefik salah.  
**Solusi**: Pastikan Anda menggunakan [`docker-compose.dokploy.yml`](file:///C:/Ryu/Projects/unms/docker-compose.dokploy.yml) dan variabel `APP_DOMAIN` telah diisi dengan domain yang mengarah (DNS A-record) ke IP server Dokploy Anda.
