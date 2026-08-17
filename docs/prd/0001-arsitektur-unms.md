# Dokumen Perancangan: Sistem Manajemen Jaringan ISP (UNMS-like)

**Stack**: Laravel 13, PHP 8.4, MySQL 8, Redis (queue + cache), Mikrotik RouterOS API, Xendit (payment gateway), SysBlast/GOWA (WhatsApp blast)
**Konvensi penamaan**: Bahasa Indonesia (tabel, kolom, model, route)

---

## 1. Ringkasan & Batasan Sistem

Sistem ini adalah aplikasi billing & manajemen jaringan ISP yang:
- Mengelola data pelanggan, alamat, dan titik lokasi (ODP/ODC, perumahan)
- Mengelola paket layanan PPPoE dan provisioning ke router Mikrotik
- Menangani penagihan (invoice) dan pembayaran via Xendit
- Mengirim pengingat tagihan via WhatsApp sebelum jatuh tempo
- Menyediakan dashboard operasional untuk 5 role internal
- Portal pelanggan berjalan di **domain terpisah**, mengonsumsi API yang sama (atau API terbatas/token-scoped)

**Prinsip arsitektur utama**:
1. **Pemisahan tegas antara DB write dan external I/O.** Panggilan ke Mikrotik, Xendit, dan WA API tidak pernah dilakukan di dalam `DB::transaction()`. Transaksi database hanya berisi operasi tulis ke MySQL, lalu job ke sistem eksternal di-dispatch **setelah commit** (`afterCommit()`).
2. **Semua panggilan ke Mikrotik bersifat asinkron via queue job**, karena RouterOS API bisa lambat/tidak stabil dan tidak boleh memblokir request web atau webhook Xendit.
3. **Idempotency wajib** di semua entry point yang bisa dipanggil berulang (webhook Xendit, job WA blast, job provisioning Mikrotik).
4. **Audit trail** di setiap perubahan status krusial (invoice, layanan, ticket) via `log_aktivitas`.

---

## 2. Role & Hak Akses

Menggunakan `spatie/laravel-permission`. Role internal (dashboard admin, domain terpisah dari pelanggan):

| Role | Deskripsi | Akses utama |
|---|---|---|
| `super_admin` | Kontrol penuh sistem | Semua modul, termasuk konfigurasi router, promo, pengguna |
| `admin` | Operasional harian | Kontak pelanggan, invoice, laporan, ticket |
| `sales` | Penjualan & pemasangan baru | Tambah pelanggan, ticket pemasangan, lihat billing terbatas |
| `noc` | Network Operation Center | Router, IP pool, status koneksi, ticket gangguan |
| `teknisi` | Lapangan | Ticket yang di-assign ke dirinya, update status pemasangan/perbaikan |

Pelanggan/customer **tidak** menggunakan role Spatie yang sama — mereka autentikasi terpisah (guard `pelanggan`) di domain portal, dengan akses hanya ke data miliknya sendiri (invoice, riwayat pembayaran, profil).

### Matriks permission (ringkas, per modul)

| Modul | super_admin | admin | sales | noc | teknisi |
|---|---|---|---|---|---|
| Kontak pelanggan | CRUD | CRUD | Create/Read | Read | Read |
| Invoice & pembayaran | CRUD | CRUD | Read | - | - |
| Router & IP pool | CRUD | Read | - | CRUD | Read |
| Paket layanan & promo | CRUD | Read | Read | - | - |
| Ticket | CRUD | CRUD | Create (pemasangan) | CRUD (gangguan) | Update (assigned) |
| Laporan keuangan | Read | Read | - | - | - |
| Pengguna & role | CRUD | - | - | - | - |
| Konfigurasi WA gateway | CRUD | - | - | - | - |

Implementasikan permission granular (`invoice.view`, `invoice.hapus`, `router.provision`, dst) lalu group ke role di seeder, bukan hardcode role check di controller — memudahkan penyesuaian tanpa migrasi ulang.

---

## 3. Skema Database (Inti)

Semua nama tabel & kolom pakai bahasa Indonesia, `snake_case`, primary key `id` (bigint unsigned), timestamps standar Laravel (`created_at`, `updated_at`), soft delete (`deleted_at`) untuk tabel transaksional penting (`pelanggan`, `layanan_pelanggan`, `invoice`).

### 3.1 Wilayah & Lokasi

```
kota            (id, nama_kota, keterangan)
kecamatan       (id, kota_id FK, nama_kecamatan, keterangan)
kelurahan       (id, kecamatan_id FK, nama_kelurahan, keterangan)
perumahan       (id, kelurahan_id FK, nama_perumahan, singkatan, keterangan)
```

### 3.2 Pelanggan

```
pelanggan
  id, no_reg (unik, format custom mis. REG-2026-000123)
  tipe_pelanggan (enum: rumah, bisnis)
  nik (nullable, 16 digit, terenkripsi/hash sebagian untuk PII)
  nama_depan, nama_belakang
  email, no_hp, telepon_rumah (nullable)
  perumahan_id FK nullable, rt, rw, no_rumah, kode_pos
  alamat_lengkap (text)
  latitude, longitude (decimal 10,7)
  status (enum: aktif, tidak_aktif, prospek)
  kode_pembayaran (unik, dipakai sbg identifier di VA Xendit)
  gambar_ktp_path (nullable)
  dibuat_oleh FK -> pengguna
  timestamps, soft_deletes

akun_pelanggan (guard terpisah untuk portal)
  id, pelanggan_id FK, username (email), password, email_verified_at
```

### 3.3 Perangkat Jaringan

```
router
  id, nama_router (unik, huruf besar+underscore)
  ip_address, port (default 8728/8729 utk API)
  username, password_terenkripsi (encrypted cast)
  deskripsi, status_koneksi (enum: online, offline, unknown)
  last_sync_at
  timestamps

odp
  id, nama_odp, perumahan_id FK nullable, kapasitas_port
  latitude, longitude
  timestamps

odp_port
  id, odp_id FK, nomor_port, status (enum: kosong, terpakai, rusak)
  layanan_pelanggan_id FK nullable

ip_pool
  id, router_id FK, nama_pool (unik)
  ip_network, cidr, rentang_ip_awal, rentang_ip_akhir
  priority_tx, priority_rx (default 8)
  timestamps

bandwidth_profile
  id, nama_bandwidth (unik, 4-30 char)
  max_limit_tx, max_limit_rx
  burst_rate_tx, burst_rate_rx
  burst_threshold_tx, burst_threshold_rx
  burst_time_tx, burst_time_rx (detik)
  limit_rate_tx, limit_rate_rx
  priority (1-8)
```

### 3.4 Layanan & Billing

```
paket_layanan
  id, nama_paket (unik, format terbatas: huruf/angka/_/[[]]/-)
  bandwidth_profile_id FK
  harga (decimal 12,2, min 10000 max 50000000)
  masa_aktif_nilai, masa_aktif_satuan (enum: hari, bulan)
  keterangan (text nullable)
  status (aktif/nonaktif)

layanan_pelanggan
  id, pelanggan_id FK, paket_layanan_id FK, router_id FK
  site_id (unik)
  ppp_username (unik), ppp_password_terenkripsi
  ip_static (nullable), odp_port_id FK nullable
  jenis_koneksi (enum: pppoe, ip_static)
  status (enum: proses, aktif, suspend, berhenti)
  tanggal_mulai, tanggal_expired
  timestamps, soft_deletes

  -- index: (status, tanggal_expired) untuk query invoice generation & reminder

invoice
  id, no_invoice (unik)
  pelanggan_id FK, layanan_pelanggan_id FK
  jumlah (decimal 12,2), jumlah_setelah_promo (decimal 12,2)
  promo_id FK nullable
  status (enum: menunggu_pembayaran, lunas, kadaluarsa, dihapus)
  tanggal_terbit, tanggal_jatuh_tempo, tanggal_lunas nullable
  metode_pembayaran (nullable, terisi setelah lunas)
  dibuat_oleh FK -> pengguna (nullable, null jika auto-generate)
  dihapus_oleh FK -> pengguna (nullable), keterangan_hapus (nullable)
  timestamps, soft_deletes

pembayaran
  id, invoice_id FK
  metode (enum: payment_gateway, manual_admin, transfer)
  referensi_transaksi (nullable, no. VA/teller)
  jumlah_dibayar (decimal 12,2)
  dibayar_pada
  dicatat_oleh FK -> pengguna (nullable, null jika via gateway)
  timestamps
```

### 3.5 Payment Gateway (Xendit)

```
transaksi_payment_gateway
  id, invoice_id FK
  gateway (default: 'xendit')
  external_id (unik — dikirim ke Xendit, dipakai untuk idempotency)
  xendit_reference_id (nullable, ID dari Xendit)
  channel (enum: virtual_account, qris, ewallet, retail_outlet)
  channel_detail (mis. kode bank / label ewallet)
  nomor_pembayaran (VA number / QR string, nullable)
  total_tagihan, fee_gateway (decimal 12,2)
  status (enum: pending, paid, expired, failed)
  expired_at
  payload_request (json), payload_response (json)
  timestamps

  -- unique index (external_id) -- kunci idempotency utama

webhook_log
  id, transaksi_payment_gateway_id FK nullable
  event_type, xendit_event_id (unik, untuk cegah proses webhook duplikat)
  payload (json), status_proses (enum: diterima, diproses, gagal, diabaikan)
  diterima_pada
```

### 3.6 Promo

```
promo
  id, kode_promo (unik), nama_promo
  jenis (enum: bonus_durasi, diskon)
  deskripsi, aturan (text)
  bayar_bulan (nullable), bonus_bulan (nullable)
  diskon_tipe (enum: persentase, nominal, nullable)
  diskon_nilai (nullable)
  minimal_nominal_invoice (nullable)
  kuota_global (nullable), kuota_per_pelanggan (nullable)
  terpakai_global (default 0)
  berlaku_dari, berlaku_sampai (nullable)
  aktif (boolean), tampil_ke_customer (boolean)

promo_penggunaan
  id, promo_id FK, pelanggan_id FK, invoice_id FK
  digunakan_pada
  -- unique (promo_id, invoice_id) agar 1 invoice tak dobel klaim promo
```

### 3.7 Ticket

```
ticket
  id, jenis (enum: pemasangan, pencabutan, gangguan, pindah_alamat)
  pelanggan_id FK, layanan_pelanggan_id FK nullable
  prioritas (enum: rendah, sedang, tinggi, darurat)
  divisi (enum: admin, customer_service, sales, noc, teknisi)
  pic_id FK -> pengguna (nullable)
  status (enum: baru, diproses, menunggu_konfirmasi, selesai, batal)
  deskripsi (text)
  dijadwalkan_pada (nullable)
  timestamps

ticket_histori
  id, ticket_id FK, catatan (text)
  status_baru, oleh_pengguna_id FK
  dibuat_pada
```

### 3.8 WA Blast

```
wa_gateway_config
  id, label, no_hp, url_api, api_key_terenkripsi
  is_default (boolean), status (aktif/nonaktif)
  limit_per_menit (default 20)

antrian_wa_blast
  id, wa_gateway_config_id FK
  no_hp_tujuan, pesan (text)
  jenis (enum: pengingat_h3, pengingat_h1, pengingat_h0, tunggakan, konfirmasi_bayar, promo, update_ticket)
  referensi_tipe, referensi_id (polymorphic: invoice/ticket/dll)
  status (enum: menunggu, diproses, terkirim, gagal)
  dijadwalkan_pada, dikirim_pada
  response_log (json nullable)
  percobaan_ke (default 0)
  timestamps

  -- unique (referensi_tipe, referensi_id, jenis) untuk cegah pengingat dobel di hari yang sama
```

### 3.9 Log & Audit

```
mikrotik_sync_log
  id, router_id FK, layanan_pelanggan_id FK nullable
  jenis_perintah (enum: buat_ppp_secret, suspend, aktifkan, hapus_ppp_secret, sync_status, buat_ip_pool, buat_queue)
  payload (json), status (enum: sukses, gagal, pending)
  response (text nullable)
  dijalankan_pada, percobaan_ke

log_aktivitas
  id, pengguna_id FK nullable, aksi, model_type, model_id
  data_lama (json nullable), data_baru (json nullable)
  ip_address, dibuat_pada
```

Gunakan `spatie/laravel-activitylog` untuk `log_aktivitas` agar tidak menulis ulang dari nol.

---

## 4. Alur Pembayaran via Xendit (dengan DB Transaction)

### 4.1 Pembuatan tagihan pembayaran

1. Invoice dibuat (manual atau via scheduler bulanan) → status `menunggu_pembayaran`.
2. Saat pelanggan memilih metode bayar di portal (atau sistem auto-generate VA saat invoice terbit), panggil **Xendit API** (di luar transaksi DB) untuk membuat VA/QRIS dengan `external_id` unik, mis. `INV-{no_invoice}-{timestamp}`.
3. Simpan hasil ke `transaksi_payment_gateway` (insert biasa, bukan bagian dari transaksi kritis).

### 4.2 Menerima Webhook Xendit (bagian paling kritis)

**Prinsip**: verifikasi → idempotency check → DB transaction (hanya tulis DB) → commit → dispatch job eksternal.

Langkah imperatif untuk agent/dev yang mengimplementasikan:

1. Endpoint `POST /webhook/xendit` menerima payload. **Verifikasi `x-callback-token`** terhadap token yang tersimpan di config (bukan di database, simpan di `.env`).
2. Cek `webhook_log` berdasarkan `xendit_event_id` — jika sudah ada dan `status_proses = diproses`, **hentikan** (return 200 tanpa proses ulang). Ini mencegah double-processing dari retry Xendit.
3. Insert `webhook_log` baru dengan status `diterima`.
4. Cari `transaksi_payment_gateway` berdasarkan `external_id` dari payload. Jika tidak ditemukan → log gagal, return 200 (jangan biarkan Xendit retry tanpa henti untuk data yang memang tidak valid).
5. **Mulai `DB::transaction()`**:
   - Lock baris invoice terkait dengan `lockForUpdate()` untuk mencegah race condition dari webhook duplikat yang lolos idempotency check di langkah 2 (defense in depth).
   - **Guard clause**: jika `invoice.status` sudah `lunas`, `COMMIT` transaksi kosong dan `return` — idempotent terhadap duplikasi.
   - Update `transaksi_payment_gateway.status` = `paid`, simpan `payload_response`.
   - Update `invoice.status` = `lunas`, `tanggal_lunas` = now, `metode_pembayaran` = channel dari Xendit.
   - Insert record `pembayaran` (jumlah_dibayar, referensi_transaksi, metode = `payment_gateway`).
   - Perpanjang `layanan_pelanggan.tanggal_expired` sesuai `masa_aktif` paket (hitung dari `tanggal_expired` lama jika masih di masa depan — untuk pembayaran lebih awal — atau dari `now()` jika sudah lewat/suspend).
   - Update `webhook_log.status_proses` = `diproses`.
   - Catat `log_aktivitas`.
6. **Commit transaksi.**
7. **Setelah commit** (pakai `DB::afterCommit()` atau dispatch job biasa setelah blok transaksi selesai, JANGAN di dalam closure transaksi):
   - Dispatch `AktifkanLayananJob` (queue: `mikrotik`) → job ini yang memanggil RouterOS API untuk un-suspend PPP secret / hapus dari address-list suspend.
   - Dispatch `KirimWaKonfirmasiPembayaranJob` (queue: `wa-blast`).
8. Return HTTP 200 ke Xendit secepat mungkin — semua kerja berat (Mikrotik, WA) sudah di-offload ke queue, bukan diproses sinkron di request webhook.

**Kenapa lock + guard clause penting**: webhook Xendit bisa terkirim lebih dari sekali (retry policy mereka). Tanpa idempotency check ganda (unique constraint di webhook_log + guard clause di dalam transaksi terkunci), invoice bisa diperpanjang dua kali atau WA konfirmasi terkirim berulang.

### 4.3 Kegagalan / Expired

Job terjadwal (`invoice:cek-kadaluarsa-va`) menandai `transaksi_payment_gateway` yang `expired_at` terlewati tanpa pembayaran sebagai `expired`, tanpa mengubah status invoice (invoice tetap `menunggu_pembayaran`, pelanggan bisa generate VA baru).

---

## 5. Integrasi Mikrotik RouterOS

### 5.1 Prinsip

- **Tidak pernah panggil RouterOS API secara sinkron di dalam HTTP request atau webhook handler.** Semua operasi tulis (create/update/delete PPP secret, queue, address-list) dijalankan lewat **queue job** dengan retry & backoff.
- **Operasi baca berkala** (status koneksi aktif, uptime) dilakukan lewat **scheduled job**, bukan query langsung tiap kali dashboard dibuka — hasilnya di-cache ke kolom lokal (`layanan_pelanggan.status`, `router.status_koneksi`, `router.last_sync_at`).
- Setiap job Mikrotik **wajib log ke `mikrotik_sync_log`** sebelum dan sesudah eksekusi (untuk audit & troubleshooting jaringan lapangan).

### 5.2 Job utama

| Job | Trigger | Aksi RouterOS |
|---|---|---|
| `BuatLayananPppJob` | Ticket pemasangan disetujui | `/ppp/secret/add` dengan profile sesuai `bandwidth_profile` |
| `AktifkanLayananJob` | Invoice lunas | Hapus dari address-list suspend / enable secret |
| `SuspendLayananJob` | Scheduler harian mendeteksi `tanggal_expired` lewat + grace period | Tambah ke address-list suspend / disable secret |
| `HapusLayananJob` | Ticket pencabutan selesai | `/ppp/secret/remove` |
| `SinkronStatusRouterJob` | Scheduler tiap 5 menit | `/ppp/active/print` → update status realtime lokal |
| `BuatIpPoolJob` | Admin menyimpan konfigurasi IP Pool baru | `/ip/pool/add` + `/queue/simple/add` |

### 5.3 Package & desain teknis

- Gunakan client RouterOS API PHP (mis. `evilfreelancer/routeros-api-php`) dibungkus dalam `App\Services\Mikrotik\RouterOsClient` — satu service per koneksi router, resolve kredensial dari `router.password_terenkripsi` (encrypted cast Laravel).
- Semua job Mikrotik implementasikan `ShouldQueue`, set `$tries = 3`, `backoff()` progresif (mis. 30s, 2m, 10m), dan `failed()` untuk menandai status `gagal` di `mikrotik_sync_log` + notifikasi ke NOC (bukan WA blast pelanggan, tapi mis. Slack/Telegram internal atau tabel notifikasi in-app).
- Queue worker terpisah (`queue:work --queue=mikrotik`) supaya kegagalan/lambatnya panggilan router tidak mengantre job WA atau job lain.

---

## 6. Alur WA Blast (Pengingat Tagihan)

### 6.1 Jadwal pengingat

Command terjadwal harian `php artisan invoice:kirim-pengingat` (via Laravel Scheduler, jalan sekali sehari pagi):

1. Query invoice `status = menunggu_pembayaran` dengan `tanggal_jatuh_tempo` = hari ini+3, +1, hari ini, dan yang sudah lewat (tunggakan, kirim ulang tiap N hari agar tidak spam).
2. Untuk tiap invoice, cek dulu di `antrian_wa_blast` apakah kombinasi (`referensi_id`, `jenis`) untuk hari ini sudah ada — kalau sudah, skip (idempotent, unique constraint jadi penjamin terakhir).
3. Insert ke `antrian_wa_blast` dengan template pesan sesuai `jenis` (H-3 ramah, H-1 lebih tegas, H0 & tunggakan mencantumkan link pembayaran).

### 6.2 Worker pengiriman

- Queue job `KirimWaBlastJob` mengonsumsi `antrian_wa_blast` yang `status = menunggu`, memanggil `wa_gateway_config.url_api` (fallback ke gateway `is_default` bila salah satu channel down).
- **Rate limiting** dihormati di level job — gunakan `Illuminate\Support\Facades\RateLimiter` per `wa_gateway_config_id` sesuai `limit_per_menit`, bukan hanya mengandalkan queue delay.
- Setelah respons API, update `status` (`terkirim`/`gagal`) dan `response_log`. Kegagalan retry maksimal 3x dengan backoff, lalu ditandai `gagal` permanen untuk ditinjau manual.

---

## 7. Rekomendasi Package Laravel

| Kebutuhan | Package |
|---|---|
| Role & permission | `spatie/laravel-permission` |
| Audit trail | `spatie/laravel-activitylog` |
| Queue monitoring | `laravel/horizon` (khususnya penting karena banyak queue: mikrotik, wa-blast, default) |
| Export laporan Excel | `maatwebsite/excel` |
| Export PDF (invoice, laporan) | `barryvdh/laravel-dompdf` |
| Admin panel/CRUD cepat | FilamentPHP v4 (selaras dengan stack Ryu di proyek lain) |
| RouterOS API client | `evilfreelancer/routeros-api-php` atau setara |
| Xendit | `xendit/xendit-php` (SDK resmi) atau Guzzle langsung ke REST API bila butuh kontrol penuh atas retry/logging |
| Enkripsi kredensial router/API key | Laravel `encrypted` cast pada kolom model, bukan enkripsi manual |
| GeoJSON/KML (ODP, peta) | `phayes/geophp` atau proses native + Haversine (sudah dipakai di proyek WifiGo Anda) |

---

## 8. Rekomendasi Fase Pengembangan

1. **Fase 1 — Fondasi**: skema database inti, role & permission, CRUD wilayah/pelanggan/router/paket layanan.
2. **Fase 2 — Billing manual**: invoice generation, pembayaran manual admin (belum Xendit), laporan dasar.
3. **Fase 3 — Integrasi Xendit**: webhook handler dengan idempotency, VA/QRIS generation, portal pelanggan (domain terpisah, API token-based).
4. **Fase 4 — Integrasi Mikrotik**: provisioning PPP, suspend/aktifkan otomatis, sinkronisasi status.
5. **Fase 5 — WA Blast & Ticket**: pengingat tagihan otomatis, modul ticket lengkap dengan histori.
6. **Fase 6 — Laporan lanjutan & Maps**: laporan keuangan bulanan dengan skor kesehatan (seperti contoh di data UNMS Anda), integrasi peta ODP/estimasi kabel.

---

## 9. Catatan Keamanan

- Password router, API key WA gateway, dan credential Xendit: **wajib** pakai Laravel `encrypted` cast atau disimpan di `.env`/secrets manager — jangan plaintext di database.
- NIK dan data KTP: pertimbangkan enkripsi at-rest untuk kolom NIK, dan simpan gambar KTP di disk **private** (bukan `public`), akses lewat signed URL sementara.
- Endpoint webhook Xendit: verifikasi token, dan pertimbangkan whitelist IP Xendit di level nginx/firewall sebagai lapisan tambahan.
- Rate limit endpoint portal pelanggan (login, request VA) untuk mencegah abuse.