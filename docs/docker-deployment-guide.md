# GOBILLING / UNMS — Docker & Deployment Guide

Dokumentasi resmi panduan kontainerisasi Docker, deployment lokal (Laravel Sail), dan deployment produksi berbasis **Dokploy** (Caddy + PHP-FPM, Horizon, Scheduler dalam satu kontainer/servis, MySQL 8.4, Redis 7, RustFS S3, dan WAHA) untuk sistem GOBILLING (ISP Billing & Network Management System).

Lihat `docs/adr/0036-single-service-dokploy-deployment.md` untuk rasionale arsitektur single-service ini (menggantikan desain decoupled `app`/`horizon`/`scheduler` sebelumnya di ADR-0001/ADR-0034).

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

GOBILLING berjalan sebagai **satu servis Dokploy, satu image, satu kontainer** per replica. Caddy, PHP-FPM, Laravel Horizon, dan scheduler (`schedule:run` loop) semuanya diawasi oleh satu proses `supervisord` di dalam kontainer yang sama. MySQL, Redis, RustFS (S3), dan WAHA berjalan sebagai servis Dokploy terpisah di luar image ini.

```text
                          [ Internet Traffic / Users ]
                                       │
                                       ▼
                   [ Dokploy Ingress / Traefik Reverse Proxy ]
                     │ Port 80 (SSL)              │ Port 9000 (SSL)
                     │ (Host: app.domain.id)      │ (Host: s3.domain.id)
                     ▼                            ▼
        ┌─────────────────────────────┐ ┌─────────────────────────┐
        │        gobilling_app        │ │     gobilling_rustfs    │
        │  (1 image, replicas: N)     │ │ (S3 MinIO Compatibility)│
        │  supervisord ─┬─ caddy      │ └────────────┬────────────┘
        │               ├─ php-fpm    │              │
        │               ├─ horizon    │              │
        │               └─ schedule   │              │
        └───────────────┬──────────────┘              │
                        │                              │
   ┌────────────────────┼──────────────────────────────┴──────────────────┐
   │                    │                              │                  │
   ▼                    ▼                              ▼                  ▼
┌────────────────┐ ┌───────────┐               ┌────────────────────┐ ┌───────────┐
│  gobilling_mysql │ │   redis   │               │   (n/a — folded    │ │gobilling_waha│
│    (MySQL 8.4)   │ │ (Redis 7) │               │   into gobilling_app)│ │(WA Sessions)│
└────────────────┘ └───────────┘               └────────────────────┘ └───────────┘
```

### Komponen Layanan:
- **`gobilling_app`**: Satu-satunya servis aplikasi Dokploy. Kontainernya menjalankan Caddy (web server, port 80), PHP-FPM (backend Laravel 13/Livewire 4/Flux), Horizon (`mikrotik-high`/`mikrotik-low`/`default`/`wa-blast` queue workers), dan loop scheduler (`schedule:run` setiap menit) — semuanya diawasi oleh `supervisord` (lihat `docker/supervisord.conf`, `docker/supervisor.d/*.conf`).
  - **Tanpa file `.env` komit**: seluruh konfigurasi berasal dari environment variable kontainer sungguhan yang di-inject Dokploy (lihat `.env.docker.example`). Laravel me-load `.env` secara *immutable* — variabel proses sungguhan selalu menang atas isi file `.env` mana pun untuk key yang sama, tapi key yang *tidak* di-set di Dokploy akan diam-diam jatuh ke isi file `.env` jika ada. `docker/entrypoint.sh` sengaja membuat `.env` kosong (bukan menyalin `.env.example` yang berisi default local-dev seperti `APP_ENV=local`/`DB_HOST=127.0.0.1`) dan akan **gagal boot (exit 1)** jika `APP_KEY` tidak di-set di environment Dokploy — mencegah container berjalan diam-diam dengan konfigurasi lokal yang salah.
  - Dockerfile juga membekukan default aman via `ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr` sebagai lantai minimum; environment variable Dokploy tetap selalu menang di atasnya.
  - Setiap perintah terjadwal di `routes/console.php` menggunakan `->onOneServer()` (Redis distributed lock) agar aman dijalankan di 2+ replica tanpa duplikasi eksekusi.
  - Migrasi database saat boot dijalankan lewat `php artisan app:migrate-once` (dibungkus `Cache::lock()`), bukan `migrate --force` langsung, agar aman terhadap boot replica konkuren saat rolling deploy.
- **`mysql`**: Database MySQL 8.4 LTS dengan dukungan query geospasial GIS (`ST_Distance_Sphere` untuk Estimasi Kabel & ODP), dikelola sebagai servis Dokploy terpisah.
- **`redis`**: Broker in-memory Redis 7 untuk queue Horizon, cache aplikasi, `onOneServer()` lock, dan sesi.
- **`rustfs`**: S3-compatible Object Storage untuk berkas bukti bayar, PDF invoice, dan avatar pelanggan melalui `spatie/laravel-medialibrary`.
- **`waha`**: WhatsApp HTTP API (Core Chromium) untuk pairing sesi staf dan transmisi notifikasi tagihan real-time.

---

## 2. File Konfigurasi & Struktur Direktori

```text
unms/
├── Dockerfile                       # Multi-stage: vendor (composer) -> frontend (vite, Node 22) -> runtime (php:8.4-fpm + caddy + horizon + scheduler)
├── docker/
│   ├── Caddyfile                    # Konfigurasi Caddy (zstd/gzip, body maks 100MB, cache aset statis, security headers, blok dotfile)
│   ├── entrypoint.sh                # Entrypoint (guard APP_KEY, maintenance mode, wait-for-services, migrate-once, bucket policy, cache warm-up, storage:link)
│   ├── php.ini                      # Runtime php.ini overrides (opcache, memory_limit 512M, upload/post 100M, dll.)
│   ├── www.conf                     # PHP-FPM pool config
│   ├── supervisord.conf             # Root supervisord config
│   └── supervisor.d/                # Per-process supervisor programs (caddy, php-fpm, horizon, schedule)
├── compose.yaml                     # Stack Development Lokal via Laravel Sail (bukan untuk produksi)
├── .env.docker.example              # Template variabel environment produksi siap pakai untuk Dokploy
└── .dockerignore                    # Optimasi build context Docker (mengabaikan node_modules, vendor, dll.)
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

Dokploy adalah panel PaaS open-source berbasis Docker dan Traefik. Repositori ini menyediakan satu `Dockerfile` yang dibangun langsung oleh Dokploy sebagai satu servis aplikasi (bukan Docker Compose multi-servis).

### Langkah 1: Buat Layanan Baru di Dokploy
1. Masuk ke dashboard **Dokploy**.
2. Pilih Project / Environment yang diinginkan.
3. Klik **Create Service** $\rightarrow$ pilih tipe **Application** (bukan Compose).
4. Beri nama layanan (misalnya `gobilling`).

### Langkah 2: Konfigurasi Git & Build Path
1. Pada tab **General**:
   - **Source**: Pilih **Git**.
   - **Repository**: Masukkan URL repositori Git Anda.
   - **Branch**: Pilih `main` (atau branch rilis produksi).
   - **Build Type**: Pilih **Dockerfile** (bukan Railpack/Nixpacks/Buildpacks). Build otomatis Railpack tidak menjalankan Caddy, Horizon, maupun migrasi dari image ini dan menghasilkan **502 Bad Gateway** (lihat [Troubleshooting](#7-troubleshooting--faq)).
   - **Dockerfile Path**: Biarkan default (`Dockerfile` di root repo).
2. Pastikan servis-servis eksternal (`mysql`, `redis`, `rustfs`, `waha`) sudah dibuat/berjalan terlebih dahulu di Dokploy dan berada di jaringan internal yang sama, sehingga `DB_HOST`, `REDIS_HOST`, `AWS_ENDPOINT`, dan `WAHA_HOST` dapat resolve ke nama servis tersebut.

### Langkah 3: Konfigurasi Environment Variables di Dokploy
Masuk ke tab **Environment** pada Dokploy dan salin seluruh isi dari template [`.env.docker.example`](../.env.docker.example). Pastikan mengisi:
- `APP_KEY`
- `APP_DOMAIN` (misal `buroq.gobilling.id`)
- `RUSTFS_DOMAIN` (misal `s3.buroq.gobilling.id`)
- `DB_PASSWORD` & `REDIS_PASSWORD`
- `WAHA_API_KEY`
- `XENDIT_SECRET_KEY`

### Langkah 4: Atur Domain & Port
Pada tab **Domains**, tambahkan `APP_DOMAIN` dengan **Container Port `80`** (Caddy di dalam image mendengarkan `:80` dengan HTTP polos; TLS ditangani Traefik) dan aktifkan HTTPS (Let's Encrypt). Port selain 80 juga menghasilkan 502.

### Langkah 5: Klik Deploy
1. Klik tombol **Deploy** di Dokploy.
2. Dokploy membangun image multi-stage (`vendor` → `frontend` → `runtime`).
3. Saat boot, `entrypoint.sh` berjalan berurutan: guard `APP_KEY` → perbaiki ownership `storage`/`bootstrap/cache` → masuk maintenance mode (`php artisan down`) → `app:wait-for-services` (DB & Redis) → `app:migrate-once` (migrasi dengan lock, aman untuk 2+ replica) → `app:ensure-public-media-bucket` → menghangatkan cache (`config:cache`, `route:cache`, `view:cache`, `event:cache`) → ownership ulang + `storage:link` → keluar maintenance mode (`php artisan up`) → `supervisord` (Caddy + PHP-FPM + Horizon + scheduler). Tidak diperlukan *post-init command* di dashboard Dokploy.
4. `HEALTHCHECK` image memanggil `http://127.0.0.1/up` (start period 30 detik).
5. Traefik otomatis menerbitkan sertifikat SSL Let's Encrypt untuk `APP_DOMAIN` dan `RUSTFS_DOMAIN`.

### Langkah 6: Inisialisasi Akun Superadmin Pertama Kali
Buka tab **Terminal** pada kontainer `gobilling_app` (atau jalankan via SSH host server):
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
| `LOG_CHANNEL` | `stderr` | Log Laravel dikirim ke stdout/stderr (terlihat di Dokploy log viewer) alih-alih `storage/logs/laravel.log` yang hilang setiap redeploy. |
| `DB_CONNECTION` | `mysql` | Driver database. |
| `DB_HOST` | `mysql` | Host servis database MySQL Dokploy (eksternal, di luar image ini). |
| `REDIS_HOST` | `redis` | Host servis Redis Dokploy (eksternal, di luar image ini). |
| `FILESYSTEM_DISK` | `s3` | Driver storage S3 untuk integrasi dengan RustFS. |
| `MEDIA_DISK` | `s3` | Disk `spatie/laravel-medialibrary`. |
| `AWS_ENDPOINT` | `https://s3.buroq.gobilling.id` | Harus bisa dijangkau **browser** (bukan nama servis internal): upload Livewire ke disk S3 memakai presigned URL langsung dari browser. Lihat `docs/adr/0037-livewire-s3-endpoint-browser-reachability.md`. |
| `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK` | `s3` | Upload sementara Livewire tetap di S3 agar aman untuk 2+ replica. |
| `WAHA_HOST` | `http://waha:3000` | URL internal endpoint engine WhatsApp WAHA. |
| `WAHA_API_KEY` | *(token rahasia)* | Kunci otentikasi API WAHA. |

Variabel opsional yang dibaca `docker/entrypoint.sh` saat boot:

| Variabel | Default | Keterangan |
| :--- | :--- | :--- |
| `WAIT_FOR_SERVICES` | `true` | Tunggu DB & Redis siap (`app:wait-for-services`) sebelum migrasi. |
| `WAIT_TIMEOUT` | `60` | Batas tunggu (detik). Jika lewat, boot tetap lanjut dengan peringatan. |
| `RUN_MIGRATIONS` | `true` | Jalankan `app:migrate-once` saat boot. |
| `ENABLE_MAINTENANCE` | `true` | Masuk/keluar maintenance mode selama boot. |
| `APP_MAINTENANCE_DRIVER` / `APP_MAINTENANCE_STORE` | `cache` / `redis` | Diset di Dockerfile agar flag maintenance dibagi lintas replica. |

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

Sistem juga menjalankan `horizon:monitor-health` terjadwal setiap 5 menit yang otomatis mengirim notifikasi ke pengguna `super_admin`/`noc` jika Horizon berhenti, dipause, atau tidak menyelesaikan job apapun dalam 15 menit terakhir.

### Pairing WhatsApp Web (WAHA)
1. Buka antarmuka GOBILLING pada menu **Integrasi / Gateway WA**.
2. Klik tombol **Pairing Sesi**.
3. Pindai kode QR yang muncul langsung menggunakan aplikasi WhatsApp staf pengelola. Sesi otomatis tersimpan pada volume permanen `gobilling_waha_sessions`.

---

## 7. Troubleshooting & FAQ

### 1. Masalah Izin Berkas Storage / Logs
Skrip [`docker/entrypoint.sh`](../docker/entrypoint.sh) otomatis memastikan direktori `storage` dan `bootstrap/cache` berizin `775` dan dimiliki oleh `www-data:www-data`. Jika perlu perbaikan manual:
```bash
docker exec -it -u 0 gobilling_app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
```

### 2. Horizon Worker Menolak Berhenti / Hang Saat Deploy
Konfigurasi `docker/supervisor.d/horizon.conf` menyertakan `stopwaitsecs=60` agar Horizon dapat menyelesaikan job MikroTik yang aktif secara aman sebelum diberhentikan. Untuk memicu restart tanpa merestart kontainer:
```bash
docker exec -it gobilling_app php artisan horizon:terminate
```

### 3. PHP-FPM Terputus Paksa Saat Redeploy
`docker/supervisor.d/php-fpm.conf` menetapkan `stopsignal=QUIT` (sinyal graceful-shutdown asli PHP-FPM) dan `stopwaitsecs=300` agar request yang sedang berjalan (maksimum `max_execution_time` di `php.ini`) sempat selesai sebelum kontainer dimatikan.

### 4. Koneksi API MikroTik Gagal dari Dalam Kontainer
Pastikan port TCP 8728/8729 ke IP RouterOS MikroTik dapat dijangkau dari host Dokploy (misal via tunnel VPN WireGuard). Uji koneksi dari dalam kontainer `app`:
```bash
docker exec -it gobilling_app nc -zv IP_ROUTER_MIKROTIK 8728
```

### 5. Migrasi Tidak Berjalan Saat 2+ Replica Boot Bersamaan
Ini normal — `php artisan app:migrate-once` menggunakan `Cache::lock()` sehingga hanya satu replica yang benar-benar menjalankan migrasi; replica lain akan mencatat pesan "Could not acquire migration lock ... Skipping." dan tetap boot normal.

### 6. Kontainer Langsung Exit dengan Pesan "APP_KEY is not set"
Ini disengaja: `docker/entrypoint.sh` menolak boot jika `APP_KEY` tidak ada di environment variable kontainer, karena image ini tidak lagi menyalin `.env.example` (yang berisi konfigurasi local-dev) sebagai fallback. Pastikan `APP_KEY` (hasil `php artisan key:generate --show`) sudah diisi di tab **Environment** Dokploy sebelum deploy.

### 7. 502 Bad Gateway dari Traefik
Penyebab paling umum:
- **Build Type bukan Dockerfile** (mis. Railpack/Nixpacks). Build tersebut tidak memakai `Dockerfile` repo sehingga tidak ada Caddy/Horizon/migrasi dari image ini. Ubah **Build Type** ke **Dockerfile** lalu redeploy.
- **Container Port di tab Domains bukan `80`**. Caddy hanya mendengarkan `:80`.
- Kontainer crash saat boot (mis. `APP_KEY` kosong) — periksa log deployment.

### 8. Situs Tetap 503 "Maintenance" Setelah Boot Gagal
`entrypoint.sh` menjalankan `php artisan down` di awal boot dan `php artisan up` di akhir. Karena flag maintenance disimpan di Redis (dibagi semua replica), kontainer yang crash di tengah boot bisa meninggalkan seluruh replica dalam mode maintenance. Perbaiki penyebab crash, lalu:
```bash
docker exec -it gobilling_app php artisan up
```
Atau set `ENABLE_MAINTENANCE=false` untuk melewati mekanisme ini.
