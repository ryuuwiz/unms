# GOBILLING / UNMS — Docker & Deployment Guide

Dokumentasi resmi panduan kontainerisasi Docker, deployment lokal (Laravel Sail), dan deployment produksi berbasis **Dokploy** (FrankenPHP, Horizon, Scheduler, MySQL 8.4, Redis 7, RustFS S3, dan WAHA) untuk sistem GOBILLING (ISP Billing & Network Management System).

---

## Daftar Isi
1. [Arsitektur & Topologi Kontainer](#1-arsitektur--topologi-kontainer)
2. [File Konfigurasi & Struktur Direktori](#2-file-konfigurasi--struktur-direktori)
3. [Panduan Local Development (Laravel Sail)](#3-panduan-local-development-laravel-sail)
4. [Panduan Deployment di Dokploy](#4-panduan-deployment-di-dokploy)
5. [Konfigurasi Environment Variable Produksi](#5-konfigurasi-environment-variable-produksi)
6. [Operasional & Pemeliharaan (Maintenance)](#6-operasional--pemeliharaan-maintenance)
7. [Troubleshooting & FAQ](#7-troubleshooting--faq)

---

## 1. Arsitektur & Topologi Kontainer

GOBILLING dibangun dengan arsitektur decoupled berbasis **FrankenPHP** (Caddy Web Server + PHP 8.4 runtime) yang memisahkan traffic HTTP, antrean latar belakang (Queue Worker), penjadwalan berkala (Scheduler), object storage, dan WhatsApp gateway ke dalam kontainer terisolasi:

```text
                          [ Internet Traffic / Users ]
                                       │
                                       ▼
                   [ Dokploy Ingress / Traefik Reverse Proxy ]
                     │ Port 80/443 (SSL)          │ Port 9000 (SSL)
                     │ (Host: app.domain.id)      │ (Host: s3.domain.id)
                     ▼                            ▼
        ┌─────────────────────────┐    ┌─────────────────────────┐
        │      gobilling_app      │    │     gobilling_rustfs    │
        │(FrankenPHP 8.4 / Caddy) │    │ (S3 MinIO Compatibility)│
        └────────────┬────────────┘    └────────────┬────────────┘
                     │                              │
   ┌─────────────────┼──────────────────────────────┴──────────────────┐
   │                 │                              │                  │
   ▼                 ▼                              ▼                  ▼
┌─────────────────┐ ┌─────────────┐        ┌────────────────────┐ ┌───────────┐
│gobilling_horizon│ │  scheduler  │        │  gobilling_mysql   │ │   redis   │
│(Laravel Horizon)│ │(Cron Daemon)│        │    (MySQL 8.4)     │ │ (Redis 7) │
└────────┬────────┘ └─────────────┘        └────────────────────┘ └───────────┘
         │
         ├─── Outbound TCP 8728/8729 ──► [ Perangkat MikroTik RouterOS ]
         │
         ▼ Internal HTTP (Port 3000)
┌─────────────────┐
│ gobilling_waha  │ ──► [ WhatsApp Web Engine / Gateway ]
│ (WAHA Sessions) │
└─────────────────┘
```

### Komponen Layanan:
- **`app`**: Server web FrankenPHP 8.4 dengan Caddy bawaan yang melayani request HTTP backend Laravel 13, Livewire 4, dan Flux UI. Mendukung kompresi gzip/zstd dan batas request body 64MB.
- **`horizon`**: Daemon worker `php artisan horizon` dengan isolasi supervisor:
  - `supervisor-high`: Khusus antrean prioritas instan `mikrotik-high` (provisi akun, isolir, buka isolir).
  - `supervisor-low`: Auto-scaling worker untuk `mikrotik-low`, `default`, dan blast notifikasi `wa-blast`.
- **`scheduler`**: Kontainer daemon scheduler `php artisan schedule:work` yang mengeksekusi rekonsiliasi MikroTik harian, pembuatan tagihan otomatis, cek isolir jatuh tempo, dan pengecekan kedaluwarsa VA Xendit.
- **`mysql`**: Database MySQL 8.4 LTS dengan dukungan query geospasial GIS (`ST_Distance_Sphere` untuk Estimasi Kabel & ODP).
- **`redis`**: Broker in-memory Redis 7 Alpine untuk queue Horizon, cache aplikasi, atomic lock `onOneServer()`, dan sesi.
- **`rustfs`**: S3-compatible High-Performance Object Storage untuk berkas bukti bayar, PDF invoice, dan avatar pelanggan melalui `spatie/laravel-medialibrary`.
- **`waha`**: WhatsApp HTTP API (Core Chromium) untuk pairing sesi staf dan transmisi notifikasi tagihan real-time. Berjalan secara privat di dalam internal docker network.

---

## 2. File Konfigurasi & Struktur Direktori

```text
unms/
├── docker/
│   ├── Dockerfile                  # Multi-stage: base -> composer-builder -> node-builder -> production
│   ├── Caddyfile                   # Konfigurasi Caddy FrankenPHP (gzip/zstd, 64M upload, Livewire cache headers)
│   └── entrypoint.sh               # Entrypoint cerdas (wait MySQL/Redis, izin storage, cache warm, auto-migrate)
├── docker-compose.yml              # Stack Produksi Dokploy (Traefik labels, dokploy-network, decoupled services)
├── compose.yaml                    # Stack Development Lokal via Laravel Sail
├── .env.docker.example             # Template variabel environment produksi siap pakai untuk Dokploy
└── .dockerignore                   # Optimasi build context Docker (mengabaikan node_modules, vendor, dll.)
```

---

## 3. Panduan Local Development (Laravel Sail)

Pengembangan lokal menggunakan Laravel Sail yang sudah dikonfigurasi di `compose.yaml`:

```bash
# 1. Salin file environment lokal
cp .env.example .env

# 2. Jalankan Sail
./vendor/bin/sail up -d

# 3. Inisialisasi Aplikasi
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

---

## 4. Panduan Deployment di Dokploy

Dokploy adalah panel PaaS open-source berbasis Docker dan Traefik. Repositori ini telah dikonfigurasi dengan file `docker-compose.yml` yang terintegrasi secara native dengan jaringan Traefik Dokploy.

### Langkah 1: Buat Layanan Baru di Dokploy
1. Masuk ke dashboard **Dokploy**.
2. Pilih Project / Environment yang diinginkan.
3. Klik **Create Service** $\rightarrow$ pilih tipe **Compose**.
4. Beri nama layanan (misalnya `gobilling`).

### Langkah 2: Konfigurasi Git & Compose Path
1. Pada tab **General**:
   - **Source**: Pilih **Git**.
   - **Repository**: Masukkan URL repositori Git Anda.
   - **Branch**: Pilih `main` (atau branch rilis produksi).
   - **Compose Path**: Biarkan default (`docker-compose.yml`).

### Langkah 3: Konfigurasi Environment Variables di Dokploy
Masuk ke tab **Environment** pada Dokploy dan salin seluruh isi dari template [`.env.docker.example`](file:///home/ryuuwiz/code/unms/.env.docker.example). Pastikan mengisi:
- `APP_KEY`
- `APP_DOMAIN` (misal `buroq.gobilling.id`)
- `RUSTFS_DOMAIN` (misal `s3.buroq.gobilling.id`)
- `DB_PASSWORD` & `REDIS_PASSWORD`
- `WAHA_API_KEY`
- `XENDIT_SECRET_KEY`

### Langkah 4: Klik Deploy
1. Klik tombol **Deploy** di Dokploy.
2. Dokploy otomatis membangun image multi-stage (PHP 8.4, Composer, Vite).
3. Kontainer `app` akan menunggu MySQL dan Redis siap, menjalankan migrasi database (`migrate --force`), membuat symlink storage, menghangatkan cache (`optimize`), dan menjalankan FrankenPHP.
4. Traefik otomatis menerbitkan sertifikat SSL Let's Encrypt untuk `APP_DOMAIN` dan `RUSTFS_DOMAIN`.

### Langkah 5: Inisialisasi Akun Superadmin Pertama Kali
Buka tab **Terminal** pada kontainer `app` (atau jalankan via SSH host server):
```bash
docker exec -it gobilling_app php artisan app:install
```

---

## 5. Konfigurasi Environment Variable Produksi

| Variabel | Default Docker | Keterangan |
| :--- | :--- | :--- |
| `APP_ENV` | `production` | Mode aplikasi produksi. |
| `APP_DEBUG` | `false` | Wajib `false` pada produksi demi keamanan. |
| `APP_URL` | `https://buroq.gobilling.id` | URL canonical utama web GOBILLING. |
| `APP_DOMAIN` | `buroq.gobilling.id` | Domain router Traefik untuk layanan web app. |
| `RUSTFS_DOMAIN` | `s3.buroq.gobilling.id` | Subdomain router Traefik untuk S3 Object Storage RustFS. |
| `DB_CONNECTION` | `mysql` | Driver database. |
| `DB_HOST` | `mysql` | Host kontainer database MySQL di jaringan internal. |
| `REDIS_HOST` | `redis` | Host kontainer Redis di jaringan internal. |
| `RUN_MIGRATIONS` | `true` | Otomatis menjalankan `migrate --force` saat kontainer `app` boot. |
| `FILESYSTEM_DISK` | `s3` | Driver storage S3 untuk integrasi dengan RustFS. |
| `WAHA_HOST` | `http://waha:3000` | URL internal endpoint engine WhatsApp WAHA. |
| `WAHA_API_KEY` | *(token rahasia)* | Kunci otentikasi API WAHA. |

---

## 6. Operasional & Pemeliharaan (Maintenance)

### Backup Basis Data MySQL
```bash
docker exec gobilling_mysql mysqldump -u gobilling -pSECRET gobilling > backup_$(date +%F_%T).sql
```

### Restore Basis Data MySQL
```bash
docker exec -i gobilling_mysql mysql -u gobilling -pSECRET gobilling < backup.sql
```

### Memeriksa Status Antrean Horizon
```bash
docker exec -it gobilling_app php artisan horizon:status
```

### Pairing WhatsApp Web (WAHA)
1. Buka antarmuka GOBILLING pada menu **Integrasi / Gateway WA**.
2. Klik tombol **Pairing Sesi**.
3. Pindai kode QR yang muncul langsung menggunakan aplikasi WhatsApp staf pengelola. Sesi otomatis tersimpan pada volume permanen `gobilling_waha_sessions`.

---

## 7. Troubleshooting & FAQ

### 1. Masalah Izin Berkas Storage / Logs
Skrip [`docker/entrypoint.sh`](file:///home/ryuuwiz/code/unms/docker/entrypoint.sh) otomatis memastikan direktori `storage` dan `bootstrap/cache` berizin `775` dan dimiliki oleh `www-data:www-data`. Jika perlu perbaikan manual:
```bash
docker exec -it -u 0 gobilling_app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```

### 2. Horizon Worker Menolak Berhenti / Hang Saat Deploy
Konfigurasi kontainer menyertakan `stop_grace_period: 60s` agar Horizon dapat menyelesaikan job MikroTik yang aktif secara aman. Untuk memicu restart tanpa merestart kontainer:
```bash
docker exec -it gobilling_app php artisan horizon:terminate
```

### 3. Koneksi API MikroTik Gagal dari Dalam Kontainer
Pastikan port TCP 8728/8729 ke IP RouterOS MikroTik dapat dijangkau dari host Dokploy (misal via tunnel VPN WireGuard). Uji koneksi dari dalam kontainer `app`:
```bash
docker exec -it gobilling_app nc -zv IP_ROUTER_MIKROTIK 8728
```
