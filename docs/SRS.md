# Software Requirement Specification (SRS)
## Sistem Manajemen Jaringan & Billing ISP (GOBILLING / UNMS)

---

### Informasi Dokumen
- **Nama Proyek**: GOBILLING (ISP Network & Billing Management System)
- **Versi Dokumen**: 1.0.0
- **Standar Format**: IEEE Std 830-1998 (*Simplified*)
- **Bahasa**: Bahasa Indonesia (dengan istilah teknis dan nama entitas sesuai codebase)
- **Target Pembaca**: Pengembang Perangkat Lunak (*Software Engineers* / *Onboarding Developers*), Analis Sistem, dan Tim Teknis ISP.

---

## 1. Pendahuluan

### 1.1 Tujuan Dokumen
Dokumen *Software Requirement Specification* (SRS) ini bertujuan untuk mendefinisikan secara lengkap dan terstruktur seluruh kebutuhan fungsional dan non-fungsional dari sistem **GOBILLING** (sebelumnya dikenal sebagai **UNMS**). Dokumen ini menjadi acuan spesifikasi teknis bagi pengembang perangkat lunak (*software engineers*) yang melakukan *onboarding*, pemeliharaan, pengembangan fitur baru, dan pengujian sistem. Seluruh kebutuhan yang dijabarkan dalam dokumen ini diekstraksi langsung dari implementasi arsitektur kode (*actual codebase*), skema basis data, kebijakan keamanan (*policies*), dan alur layanan (*services*).

### 1.2 Ruang Lingkup Sistem
GOBILLING adalah platform manajemen terpadu kelas produksi (*production-grade*) untuk penyelenggara jasa internet (*Internet Service Provider* / ISP) berskala kecil hingga menengah. Sistem ini mengintegrasikan:
1. **Manajemen Pelanggan & Layanan (CRM & Subscriptions)**: Registrasi data pelanggan, pengelolaan multi-layanan/multi-site per pelanggan, enkripsi dokumen identitas (KTP) *at-rest*, dan *watermarking* dinamis.
2. **Infrastruktur Jaringan & Provisi MikroTik RouterOS**: Integrasi langsung dengan MikroTik RouterOS melalui RouterOS API (port 8728) untuk provisi otomatis PPP Secret, alokasi IP Pool / IP Statis, Profil Bandwidth biner, pemantauan perangkat (*health check*), isolir otomatis, dan rekonsiliasi konfigurasi.
3. **Distribusi Fiber Optik (ODP)**: Manajemen Optical Distribution Point (ODP), kapasitas port, status port terminasi, serta visualisasi geospasial dan kalkulator estimasi kebutuhan kabel *drop*.
4. **Billing & Invoicing Terotomasi**: Siklus penagihan berkala bulanan, penerbitan invoice berbasis periode (`INV-YYYYMM-NNNNNN`), pencegahan duplikasi tagihan (*billing idempotency*), dan skema promosi/diskon.
5. **Penerimaan Pembayaran & Gateway Digital**: Pembayaran tunai/manual oleh staf admin dan pembayaran digital terotomasi menggunakan **Xendit Payment Gateway** (Virtual Account & QRIS) dengan verifikasi token *webhook* terenkripsi.
6. **Tiket Layanan & Operasional Lapangan**: Pelacakan berkas kerja permohonan pemasangan, aduan gangguan, pencabutan, dan pindah alamat dengan perhitungan target SLA dinamis, alokasi multi-divisi, dan riwayat status *immutable*.
7. **Portal Pelanggan Mandiri (Customer Self-Service)**: Akses terisolasi (*guard* `pelanggan`) bagi klien untuk klaim akun, melihat tagihan, dan membayar secara *real-time*.
8. **Administrasi & Keamanan**: *Multi-guard authentication*, *Role-Based Access Control* (RBAC) via Spatie Permission, otentikasi biometrik/Passkeys (FIDO2/WebAuthn), impersonasi pengguna oleh `super_admin`, dan audit *activity log*.

### 1.3 Definisi, Akronim, dan Istilah Domain (*Ubiquitous Language*)

| Istilah | Definisi Domain |
| :--- | :--- |
| **No. Registrasi (`no_reg`)** | Pengenal unik pelanggan dengan format baku `[Prefix][DDMMYYYY][Counter]` (contoh: `WG2309202601`). |
| **Site ID (`site_id`)** | Pengenal unik titik instalasi fisik layanan internet pelanggan (format: `SITE-XXXXXXXX`). |
| **PPP Username** | Kredensial autentikasi PPPoE pelanggan di RouterOS dengan format `{No.Reg}_{NNNNN}` (5-digit *CSPRNG token*). |
| **Profil Bandwidth** | Definisi limit kecepatan data (*max limit, burst rate, priority*) dalam satuan **Mbps** yang dikonversi ke **bps biner** ($1\text{ Mbps} = 1.048.576\text{ bps}$) saat dikirim ke MikroTik. |
| **IP Pool** | Blok alokasi subnet IPv4 (Network, CIDR, Range Awal/Akhir) yang terikat pada suatu Router MikroTik. |
| **ODP** | *Optical Distribution Point*, titik terminasi fisik distribusi kabel serat optik luar ruang dengan kapasitas port (4, 8, 16, 24, 32). |
| **Invoice** | Dokumen tagihan pembayaran resmi dengan format penomoran terpusat `INV-YYYYMM-NNNNNN`. |
| **Periode Tagihan** | Siklus bulan penagihan layanan berformat `YYYY-MM` yang menjamin aturan 1 invoice aktif per layanan per periode. |
| **Isolir** | Kondisi pemblokiran/pembatasan akses internet pelanggan akibat keterlambatan pembayaran invoice. |
| **SLA** | *Service Level Agreement*, batas waktu target penyelesaian tiket yang dihitung otomatis berdasarkan skala prioritas (*Kritis: 4 jam, Tinggi: 12 jam, Sedang: 24 jam, Rendah: 48 jam*). |
| **Impersonasi** | Fitur bagi `super_admin` untuk masuk sementara ke sesi staf lain atau portal pelanggan tanpa sandi untuk keperluan audit & investigasi teknis. |

---

## 2. Deskripsi Umum Sistem

### 2.1 Arsitektur Perangkat Lunak
GOBILLING dibangun dengan arsitektur **Monolith Modern Reaktif** berbasis framework **Laravel 12**, **Livewire 4**, dan **Flux UI**:
- **Backend**: PHP 8.4+ dengan *strict type hinting*, *constructor property promotion*, dan *native backed enums*.
- **Frontend / UI**: *Full-stack reactive server-driven components* menggunakan Livewire 4 & Volt yang di-styling dengan Tailwind CSS v4 dan komponen Flux UI. Interaktivitas geospasial menggunakan Leaflet.js dan visualisasi data menggunakan ApexCharts.
- **Database Engine**: MariaDB 10.6+ / MySQL 8.0+ dengan dukungan *transaction row locking* (`lockForUpdate`), *soft deletes*, *encrypted columns* (AES-256-CBC via Laravel Encrypted Cast), dan *functional unique index*.
- **Network Interface**: Sockets API langsung ke MikroTik RouterOS (Port 8728) tanpa pustaka eksternal pihak ketiga yang tidak terkelola.
- **Payment Interface**: Xendit REST API v2 (Hosted Invoices & Callbacks).

### 2.2 Karakteristik Pengguna (*User Roles*)

| Peran (*Role*) | Guard | Deskripsi Tanggung Jawab & Hak Akses |
| :--- | :--- | :--- |
| **`super_admin`** | `web` | Administrator tertinggi sistem; memiliki seluruh hak akses izin (*all permissions*), konfigurasi payment gateway, profil perusahaan, audit log, dan kemampuan impersonasi staf/pelanggan. |
| **`admin`** | `web` | Staf operasional & kasir; mengelola data pelanggan, registrasi layanan, penerbitan invoice, penerimaan pembayaran manual, manajemen promo, dan laporan keuangan. |
| **`sales`** | `web` | Staf penjualan lapangan; registrasi prospek/pelanggan baru, upload dokumen KTP & kontrak pemasangan, dan pembuatan tiket survei/pemasangan baru. |
| **`noc`** | `web` | Tim Network Operation Center; mengelola konfigurasi teknis MikroTik Router, IP Pool, Profil Bandwidth, ODP, provisi perangkat, dan tiket gangguan teknis. |
| **`teknisi`** | `web` | Petugas teknis lapangan; menangani tiket pekerjaan yang ditugaskan sebagai *Person in Charge* (PIC), memperbarui status pekerjaan, dan mencatat log progres penanganan. |
| **`pelanggan`** | `pelanggan` | Pengguna akhir layanan internet; mengakses Portal Pelanggan untuk melihat tagihan, membayar via gateway Xendit, dan mengunduh kuitansi PDF. |

### 2.3 Batasan dan Aturan Bisnis Sistem (*Constraints & Business Rules*)

1. **Aturan Multi-Layanan (*Granular Billing & Multi-Site*)**: Satu `Pelanggan` dapat memiliki banyak `LayananPelanggan` (relasi 1:N). Setiap layanan memiliki `site_id`, `ppp_username`, alokasi `router_id`, dan siklus invoice terpisah. Jika salah satu layanan menunggak, isolir hanya dilakukan pada PPP Secret layanan tersebut tanpa memutus layanan lain milik pelanggan yang sama.
2. **Aturan Format PPP Username**: Wajib berformat `{no_reg}_{NNNNN}` dengan suffix 5-digit angka acak (*CSPRNG token* `10000`–`99999`) dan unik di seluruh sistem.
3. **Idempotensi Penagihan (*Invoice Period Uniqueness*)**: Pada level basis data, diberlakukan *functional unique constraint* untuk pasangan `(layanan_pelanggan_id, periode_tagihan)` pada invoice yang tidak berstatus `dibatalkan` dan belum dihapus (*soft-deleted*). Hal ini mencegah penerbitan tagihan ganda pada periode penagihan yang sama.
4. **Alokasi Gateway & Remote Address**: Parameter `remote-address` pada PPP Secret MikroTik diisi alamat IP Statis (jika layanan bertipe IP Static). Jika pelanggan bertipe alokasi dinamis via IP Pool, `remote-address` dikosongkan agar RouterOS mengalokasikan IP dari subnet IP Pool, dan `local-address` diisi alamat gateway IP Pool terkait.
5. **Migrasi Router Otomatis (*Router Migration Cleanup*)**: Ketika `router_id` pada suatu `LayananPelanggan` diubah, *Eloquent Observer* secara otomatis menjadwalkan job antrean (`CleanupPppSecretOnOldRouterJob`) untuk menghapus secret pada router lama agar tidak terjadi konflik duplikasi identitas di jaringan.
6. **Penghapusan Berjenjang yang Dilindungi (*Guarded Deletion Wilayah*)**: Data master wilayah (`Kota`, `Kecamatan`, `Kelurahan`, `Perumahan`) tidak dapat dihapus jika masih memiliki relasi anak aktif atau data pelanggan/ODP yang terhubung.
7. **Penyimpanan Dokumen Terenkripsi & Watermarking**: Berkas KTP pelanggan disimpan secara terenkripsi di media penyimpanan lokal privat (`Crypt::encryptString`). Saat diakses oleh staf berwenang, berkas didekripsi *on-the-fly* dan disematkan teks *watermark* dinamis berupa nama staf dan *timestamp* pengaksesan guna mematuhi UU Pelindungan Data Pribadi (UU PDP).

---

## 3. Kebutuhan Fungsional (*Functional Requirements*)

### Modul 1: Autentikasi, Keamanan, & Impersonasi

#### [RF-01] Autentikasi Staf Backoffice
- **Deskripsi**: Staf internal dapat masuk (*login*) ke sistem backoffice menggunakan email dan kata sandi atau otentikasi biometrik/kunci keamanan fisik (Passkeys).
- **Aktor / Role**: `super_admin`, `admin`, `sales`, `noc`, `teknisi` (*Guard*: `web`).
- **Alur Kerja**:
  - *Input*: Email, password, atau credential Passkey (WebAuthn).
  - *Proses*: Sistem memverifikasi kredensial staf pada tabel `users`, memastikan kolom `status == 'active'`, memperbarui `last_login_at`, dan membuat sesi autentikasi.
  - *Output*: Pengguna diarahkan ke halaman `/dashboard`.

#### [RF-02] Manajemen Keamanan & Passkeys Staf
- **Deskripsi**: Staf dapat mendaftarkan kunci keamanan biometrik/hardware (Passkeys) dan mengelola Two-Factor Authentication (2FA).
- **Aktor / Role**: Seluruh staf aktif (*Middleware*: `auth`, `password.confirm`, `impersonate.protect`).
- **Alur Kerja**:
  - *Input*: Pendaftaran perangkat WebAuthn / FIDO2 via komponen Livewire `Settings\Security`.
  - *Proses*: Pendaftaran disimpan pada tabel `passkeys` yang terhubung ke `user_id`.
  - *Output*: Konfirmasi kunci keamanan aktif dan kredensial tersimpan.

#### [RF-03] Impersonasi Pengguna & Pelanggan
- **Deskripsi**: `super_admin` dapat masuk sementara sebagai staf lain atau sebagai akun pelanggan portal untuk melakukan *troubleshooting* atau verifikasi hak akses tanpa membutuhkan kata sandi.
- **Aktor / Role**: `super_admin` (*Controller*: `ImpersonateController`).
- **Alur Kerja**:
  - *Input*: ID pengguna target dan jenis guard (`web` atau `pelanggan`) via rute `/impersonate/take/{id}/{guardName?}`.
  - *Proses*: Sistem mencatat audit log aktivitas di `activity_log`, menetapkan ID sesi impersonasi, dan mengalihkan guard otentikasi aktif. Fitur ganti password dan pengaturan keamanan sensitif otomatis dikunci selama impersonasi aktif (`impersonate.protect`).
  - *Output*: Sesi impersonasi aktif; banner navigasi menampilkan opsi "Tinggalkan Impersonasi" (`/impersonate/leave`).

---

### Modul 2: Manajemen Pengguna & Peran (RBAC)

#### [RF-04] Manajemen Pengguna Staf
- **Deskripsi**: Mengelola akun staf internal (tambah, lihat, ubah, nonaktifkan).
- **Aktor / Role**: Izin `pengguna.lihat`, `pengguna.buat`, `pengguna.ubah`, `pengguna.hapus`.
- **Alur Kerja**:
  - *Input*: Nama, email, nomor HP, peran (*roles*), status akun (`active`/`inactive`), password.
  - *Proses*: Validasi integritas email unik, enkripsi password via Argon2id/Bcrypt, dan penetapan role Spatie Permission.
  - *Output*: Data staf tersimpan pada tabel `users` dan `model_has_roles`.

#### [RF-05] Manajemen Peran & Izin (*Role & Permissions*)
- **Deskripsi**: Mengonfigurasi hak akses berbasis role dan pemetaan granular izin akses modul.
- **Aktor / Role**: Izin `peran.lihat`, `peran.buat`, `peran.ubah`, `peran.hapus` (Khusus `super_admin`).
- **Alur Kerja**:
  - *Input*: Nama peran dan daftar *checkbox* permission yang diaktifkan pada komponen `Roles\Edit`.
  - *Proses*: Sinkronisasi tabel `roles` dan `role_has_permissions`, lalu reset cache permission registrar (`PermissionRegistrar::forgetCachedPermissions()`).
  - *Output*: Matriks izin akses staf terbarui secara instan.

---

### Modul 3: Manajemen Area & Wilayah

#### [RF-06] Manajemen Hierarki Wilayah
- **Deskripsi**: Mengelola master data cakupan area ISP berjenjang: Kota $\rightarrow$ Kecamatan $\rightarrow$ Kelurahan $\rightarrow$ Perumahan/Cluster.
- **Aktor / Role**: Izin `wilayah.lihat`, `wilayah.buat`, `wilayah.ubah`, `wilayah.hapus`.
- **Alur Kerja**:
  - *Input*: Nama wilayah, relasi induk (*foreign key*), singkatan, catatan keterangan, titik koordinat latitude/longitude, dan berkas batas poligon cakupan (GeoJSON) pada level perumahan.
  - *Proses*: Validasi data berjenjang; pada operasi hapus diberlakukan *guarded deletion* (penolakan penghapusan bila terdapat entitas anak atau data pelanggan/ODP yang bergantung).
  - *Output*: Data tersimpan pada tabel `kota`, `kecamatan`, `kelurahan`, dan `perumahan`.

---

### Modul 4: Profil Bandwidth & Paket Layanan

#### [RF-07] Manajemen Profil Bandwidth
- **Deskripsi**: Mengonfigurasi batasan kecepatan data (*bandwidth profile*) dalam satuan standar Mbps untuk provisi RouterOS.
- **Aktor / Role**: Izin `profil_bandwidth.lihat`, `profil_bandwidth.buat`, `profil_bandwidth.ubah`, `profil_bandwidth.hapus`.
- **Alur Kerja**:
  - *Input*: Nama profil, `max_limit_tx` (Upload Mbps), `max_limit_rx` (Download Mbps), parameter *burst* opsional (`burst_rate`, `burst_threshold`, `burst_time`, `limit_rate`), dan nilai antrean prioritas (1–8).
  - *Proses*: Data divalidasi dan disimpan dalam format angka Mbps pada tabel `profil_bandwidth`. Model menyediakan *method* otomatis untuk menghasilkan format string RouterOS (contoh: `10M/20M`).
  - *Output*: Profil bandwidth siap dihubungkan ke paket layanan dan di-push ke Simple Queue / PPP Profile MikroTik.

#### [RF-08] Manajemen Katalog Paket Layanan
- **Deskripsi**: Mengelola katalog produk langganan internet ISP.
- **Aktor / Role**: Izin `paket_layanan.lihat`, `paket_layanan.buat`, `paket_layanan.ubah`, `paket_layanan.hapus`.
- **Alur Kerja**:
  - *Input*: Nama paket, relasi `profil_bandwidth_id`, harga langganan (Rp), nilai masa aktif (contoh: `1`), satuan masa aktif (`bulan`/`hari`), status (`aktif`/`non_aktif`).
  - *Proses*: Validasi relasi bandwidth dan penyimpanan data pada tabel `paket_layanan`.
  - *Output*: Paket layanan siap dipilih saat staf melakukan registrasi layanan pelanggan.

---

### Modul 5: Jaringan, Router, & IP Pool

#### [RF-09] Manajemen Perangkat Router MikroTik
- **Deskripsi**: Menghubungkan dan memantau perangkat MikroTik RouterOS sebagai gateway layanan ISP.
- **Aktor / Role**: Izin `router.lihat`, `router.buat`, `router.ubah`, `router.hapus`, `router.provision`, `router.sync`.
- **Alur Kerja**:
  - *Input*: Nama router, alamat IP, port API (default 8728), username API, password RouterOS.
  - *Proses*: Password disimpan dengan enkripsi AES-256 (`password_terenkripsi`). Sistem melakukan uji koneksi socket API, mengambil informasi perangkat (`board_name`, `routeros_version`, `cpu_load`, `memory_free`, `uptime`), dan memperbarui status koneksi (`online`/`offline`/`maintenance`).
  - *Output*: Data tersimpan pada tabel `router` dengan log sinkronisasi di `mikrotik_job_logs`.

#### [RF-10] Manajemen Subnet IP Pool
- **Deskripsi**: Mengonfigurasi blok alokasi alamat IPv4 yang terikat pada router tertentu untuk distribusi IP dinamis PPPoE atau IP statis.
- **Aktor / Role**: Izin `ip_pool.lihat`, `ip_pool.buat`, `ip_pool.ubah`, `ip_pool.hapus`.
- **Alur Kerja**:
  - *Input*: Relasi `router_id`, nama pool, alamat IP network, CIDR (contoh: 24), rentang IP awal, rentang IP akhir, prioritas antrean TX/RX.
  - *Proses*: Validasi format IPv4 dan rentang IP; kalkulasi otomatis alamat IP gateway subnet ($Network + 1$).
  - *Output*: Data tersimpan pada tabel `ip_pool` dan disinkronkan ke menu `/ip/pool` pada RouterOS terkait.

#### [RF-11] Rekonsiliasi & Pemulihan PPP Secret (*Router Reconciliation*)
- **Deskripsi**: Memeriksa keselarasan data antara basis data GOBILLING dengan konfigurasi aktual di RouterOS, memulihkan secret yang hilang, serta mendeteksi *orphaned secret*.
- **Aktor / Role**: Izin `router.sync` (NOC / Super Admin, atau via CLI `php artisan mikrotik:recover-ppp`).
- **Alur Kerja**:
  - *Input*: Target `router_id`.
  - *Proses*: `MikrotikService` mengambil daftar secret dari router via API, membandingkannya dengan record `layanan_pelanggan`, mem-push konfigurasi yang hilang, dan mencatat *unmanaged accounts* ke log audit.
  - *Output*: Laporan status rekonsiliasi dan log eksekusi pada `mikrotik_job_logs`.

---

### Modul 6: Distribusi Fiber Optik (ODP & Port)

#### [RF-12] Manajemen Optical Distribution Point (ODP)
- **Deskripsi**: Mengelola master data titik terminasi ODP, kapasitas port fisik, dan lokasi geografis tiang.
- **Aktor / Role**: Izin `odp.lihat`, `odp.buat`, `odp.ubah`, `odp.hapus`.
- **Alur Kerja**:
  - *Input*: Nama ODP, relasi `perumahan_id`, kapasitas port (4, 8, 16, 24, 32), koordinat latitude/longitude, dan keterangan/PON.
  - *Proses*: Sistem menyimpan data ODP pada tabel `odp` dan secara otomatis mengenerasi baris port fisik pada tabel `odp_port` dengan status default `kosong`.
  - *Output*: Record ODP beserta N-port anak siap dialokasikan ke layanan pelanggan.

#### [RF-13] Impor Massal ODP via KML / GeoJSON
- **Deskripsi**: Fasilitas impor massal data ODP hasil survei lapangan dari file Google Earth (.kml) atau GIS (.geojson).
- **Aktor / Role**: Izin `odp.buat` (NOC / Super Admin via komponen `Odp\Index`).
- **Alur Kerja**:
  - *Input*: Berkas biner `.kml` atau `.geojson`.
  - *Proses*: `KmlParser` / `GeoJsonParser` mengekstraksi nama ODP, koordinat spasial, dan metadata; sistem menyajikan pratinjau tabel validasi sebelum eksekusi penyimpanan ke basis data.
  - *Output*: ODP massal terbuat beserta port otomatis.

---

### Modul 7: Manajemen Pelanggan (CRM & Dokumen Terenkripsi)

#### [RF-14] Registrasi & Manajemen Master Pelanggan
- **Deskripsi**: Pendaftaran data identitas pelanggan baru dan pembaruan kontak/alamat.
- **Aktor / Role**: Izin `pelanggan.lihat`, `pelanggan.buat`, `pelanggan.ubah`, `pelanggan.hapus`. (Sales hanya dapat mengubah/menghapus pelanggan yang didaftarkannya sendiri).
- **Alur Kerja**:
  - *Input*: Nama depan, nama belakang, NIK, email, no HP, telepon rumah, relasi `perumahan_id`, alamat lengkap, RT/RW, koordinat lat/long, tipe pelanggan (`rumah`/`bisnis`/`kantor`/`apartemen`).
  - *Proses*: Sistem menghasilkan nomor registrasi unik `no_reg` (format: `BFDDMMYYYYNN`) dan `kode_pembayaran` 12-digit secara otomatis; NIK dienkripsi secara *at-rest*; normalisasi format nomor HP ke standar `08...`; pembuatan akun portal default jika email diisi.
  - *Output*: Data tersimpan pada tabel `pelanggan` dan audit log dicatat di `activity_log`.

#### [RF-15] Pengelolaan Berkas KTP Terenkripsi & Watermarking
- **Deskripsi**: Unggah dan peninjauan dokumen identitas KTP dengan pengamanan enkripsi dan *watermarking* dinamis.
- **Aktor / Role**: Izin `pelanggan.lihat_ktp`, `pelanggan.unggah_dokumen`, `pelanggan.hapus_dokumen` (dikontrol via `PelangganPolicy`).
- **Alur Kerja**:
  - *Input*: Berkas gambar KTP (.jpg/.png) via form registrasi / edit pelanggan.
  - *Proses*: `CustomerDocumentService` mengenkripsi payload berkas (`Crypt::encryptString`) dan menyimpannya di disk privat `local` via Spatie MediaLibrary. Saat diakses via endpoint `/pelanggan/{pelanggan}/ktp/preview`, sistem mendekripsi berkas dan membubuhkan stempel *watermark* dinamis memuat nama staf dan *timestamp*.
  - *Output*: Stream gambar ter-watermark dikirim ke browser staf tanpa mengekspos berkas mentah.

---

### Modul 8: Data Registrasi Billing & Layanan Pelanggan

#### [RF-16] Pendaftaran Layanan Internet Pelanggan (*Multi-Site Subscription*)
- **Deskripsi**: Menghubungkan pelanggan dengan paket layanan, router gateway, subnet IP Pool / IP Statis, port ODP, dan kredensial PPPoE.
- **Aktor / Role**: Izin `layanan_pelanggan.buat`, `layanan_pelanggan.ubah`, `layanan_pelanggan.lihat`.
- **Alur Kerja**:
  - *Input*: `pelanggan_id`, `paket_layanan_id`, `router_id`, `ip_pool_id` (opsional jika router single-pool), `odp_port_id` (opsional), nama site/label, alamat pemasangan spesifik, koordinat site, `jenis_koneksi` (`pppoe`/`ip_static`), `ip_static` (jika jenis static), `tanggal_mulai`.
  - *Proses*: Sistem mengenerasi `site_id` (`SITE-XXXXXXXX`), `ppp_username` unik (`{no_reg}_{NNNNN}`), mengenkripsi kata sandi PPP, memvalidasi anti-duplikasi layanan aktif pada paket/router yang sama, mengunci status port ODP menjadi `terpakai`, dan menjadwalkan provisi ke MikroTik.
  - *Output*: Data tersimpan pada tabel `layanan_pelanggan` dengan status awal `proses`.

#### [RF-17] Provisi Otomatis ke MikroTik RouterOS
- **Deskripsi**: Pipeline sinkronisasi data langganan ke router fisik MikroTik secara berurutan.
- **Aktor / Role**: Sistem terotomasi / NOC via `MikrotikService`.
- **Alur Kerja**:
  - *Input*: Record `LayananPelanggan`.
  - *Proses*: Memeriksa koneksi router $\rightarrow$ Memastikan Profil Bandwidth dan IP Pool ada di router $\rightarrow$ Menambahkan/memperbarui entri `/ppp/secret` dengan `local-address` dan `remote-address` yang sesuai $\rightarrow$ Memperbarui kolom `provisioning_status = 'terprovisi'` dan `terprovisi_pada = now()`.
  - *Output*: Kredensial PPPoE aktif pada router dan siap digunakan perangkat ONT/modem pelanggan.

---

### Modul 9: Invoicing & Tagihan Otomatis

#### [RF-18] Penerbitan Tagihan Otomatis & Manual
- **Deskripsi**: Menerbitkan invoice resmi per periode penagihan (`YYYY-MM`) untuk layanan internet aktif.
- **Aktor / Role**: Izin `invoice.buat` (Staf Billing) atau Console Scheduler (`php artisan billing:generate-invoices`).
- **Alur Kerja**:
  - *Input*: `layanan_pelanggan_id`, kode promo (opsional), `tanggal_jatuh_tempo` (default H+7), `periode_tagihan`.
  - *Proses*: `BillingService::generateInvoice` memeriksa idempotensi; jika invoice aktif untuk periode tersebut sudah ada, proses mengembalikan record lama. Jika baru, sistem menghitung nominal setelah diskon promo, mengenerasi `no_invoice` (`INV-YYYYMM-NNNNNN`), dan menyimpan tagihan dengan status `menunggu_pembayaran`.
  - *Output*: Record tersimpan pada tabel `invoice` dan kuota promo (jika digunakan) terpotong.

#### [RF-19] Pembatalan Invoice Resmi
- **Deskripsi**: Membatalkan tagihan yang salah/koreksi tanpa menghapus jejak audit fisik.
- **Aktor / Role**: Izin `invoice.hapus` (Admin / Super Admin).
- **Alur Kerja**:
  - *Input*: ID Invoice dan alasan pembatalan (`keterangan_hapus`).
  - *Proses*: Mengubah status invoice menjadi `dibatalkan`, mencatat `dihapus_oleh`, dan melepaskan pemakaian promo terkait (jika ada).
  - *Output*: Status invoice menjadi `dibatalkan`; indeks unik periode terlepas sehingga tagihan baru dapat diterbitkan.

#### [RF-20] Unduh & Cetak Invoice / Kuitansi PDF
- **Deskripsi**: Menghasilkan dokumen invoice atau kuitansi pembayaran resmi berformat PDF.
- **Aktor / Role**: Izin `invoice.cetak` (Staf) atau Pelanggan terotentikasi (Guard: `pelanggan`).
- **Alur Kerja**:
  - *Input*: ID Invoice via rute `/invoice/{invoice}/cetak` atau `/portal/tagihan/{invoice}/cetak`.
  - *Proses*: `InvoicePdfController` merender template Blade yang memuat kop profil perusahaan (`Perusahaan`), rincian tagihan, status pembayaran, dan stempel lunas digital menggunakan library DomPDF.
  - *Output*: File PDF ter-stream ke browser pengguna.

---

### Modul 10: Pembayaran & Integrasi Payment Gateway Xendit

#### [RF-21] Pencatatan Pembayaran Manual / Offline
- **Deskripsi**: Menerima dan mencatat pembayaran tagihan secara tunai atau transfer manual di kantor ISP.
- **Aktor / Role**: Izin `pembayaran.catat` (Admin Billing / Kasir).
- **Alur Kerja**:
  - *Input*: ID Invoice, metode bayar (`manual_admin`/`transfer`), jumlah bayar, tanggal bayar, referensi transaksi/bukti transfer, catatan.
  - *Proses*: `BillingService::prosesPembayaranManual` mengunci baris invoice (`lockForUpdate`), memverifikasi status belum lunas, mencatat pembayaran di `pembayaran`, memperbarui invoice menjadi `lunas`, memperpanjang `tanggal_expired` pada `layanan_pelanggan` sesuai durasi paket (+ bonus promo), dan mengembalikan status layanan ke `aktif` (membuka isolir di MikroTik jika sebelumnya terisolir).
  - *Output*: Invoice lunas, layanan pelanggan aktif diperpanjang, kuitansi siap dicetak.

#### [RF-22] Penerbitan Sesi Pembayaran Digital Xendit (*Hosted Checkout*)
- **Deskripsi**: Pelanggan atau staf menerbitkan tautan pembayaran digital Xendit Hosted Invoice untuk tagihan aktif.
- **Aktor / Role**: Pelanggan Portal atau Admin Billing via `XenditPaymentService`.
- **Alur Kerja**:
  - *Input*: ID Invoice yang berstatus `menunggu_pembayaran`.
  - *Proses*: Sistem memeriksa konfigurasi fee di `pengaturan_gateway` $\rightarrow$ Mengirim request pembuatan invoice ke Xendit REST API v2 $\rightarrow$ Menyimpan ID invoice Xendit dan checkout URL (`xendit_invoice_url`) pada invoice serta membuat transaksi di `transaksi_payment_gateway`.
  - *Output*: Pengguna dialihkan ke halaman hosted checkout Xendit (memuat opsi VA BCA/BNI/BRI/Mandiri, QRIS, E-Wallet, Alfamart/Indomaret).

#### [RF-23] Pemrosesan Webhook Pembayaran Xendit Terenkripsi & Idempoten
- **Deskripsi**: Menangani panggilan balik (*HTTP callback / webhook*) otomatis dari Xendit saat pembayaran berhasil diselesaikan oleh pelanggan.
- **Aktor / Role**: Endpoint Publik Terproteksi `/webhook/xendit` (*Middleware*: `ValidateXenditCallbackToken`).
- **Alur Kerja**:
  - *Input*: Payload JSON webhook dari Xendit dan HTTP Header `x-callback-token`.
  - *Proses*:
    1. Middleware memvalidasi token verifikasi terhadap `config('services.xendit.webhook_token')`; *abort 401* jika tidak valid.
    2. `XenditWebhookController` memeriksa `xendit_event_id` pada tabel `webhook_log`. Jika event ID sudah berstatus `diproses`, sistem langsung merespons `200 OK` (idempoten).
    3. Jika baru, sistem mencatat log di `webhook_log`, membuka database transaction dengan `lockForUpdate` pada invoice terkait.
    4. Jika status `PAID` / `SETTLED`: mencatat `pembayaran`, menandai invoice `lunas`, memperbarui `transaksi_payment_gateway` menjadi `paid`, memperpanjang masa aktif `layanan_pelanggan`, membuka blokir isolir di MikroTik, dan mendispatch `InvoicePaidEvent`.
    5. Jika status `EXPIRED`: memperbarui status transaksi menjadi `expired`.
  - *Output*: Response JSON `200 OK` ("Payment processed successfully").

---

### Modul 11: Promo & Diskon

#### [RF-24] Manajemen Program Promo
- **Deskripsi**: Mengelola program promosi potongan harga (nominal/persentase) atau bonus durasi masa aktif.
- **Aktor / Role**: Izin `promo.lihat`, `promo.buat`, `promo.ubah`, `promo.hapus`.
- **Alur Kerja**:
  - *Input*: Kode promo, nama promo, jenis (`diskon`/`bonus_durasi`), tipe diskon (`persentase`/`nominal`), nilai diskon, syarat `minimal_nominal_invoice`, kuota global/per pelanggan, tanggal berlaku dari/sampai, status aktif.
  - *Proses*: Validasi integritas kode promo unik dan penyimpanan pada tabel `promo`.
  - *Output*: Promo aktif yang otomatis dapat diaplikasikan saat proses pembuatan invoice.

---

### Modul 12: Tiket Layanan, Aduan Gangguan, & SLA

#### [RF-25] Pembuatan Tiket Layanan & Penanganan Masalah
- **Deskripsi**: Membuat berkas kerja permohonan layanan baru, penanganan gangguan, pencabutan, atau pindah alamat.
- **Aktor / Role**: Izin `ticket.buat` (Staf: CS, Sales, Admin, NOC) (tiket tidak lagi dapat dibuat dari Portal Pelanggan, ADR-0040).
- **Alur Kerja**:
  - *Input*: Jenis tiket (`pemasangan`/`pencabutan`/`gangguan`/`pindah_alamat`), `pelanggan_id`, `layanan_pelanggan_id` (opsional), skala prioritas (`rendah`/`sedang`/`tinggi`/`darurat`), divisi penanggung jawab (`admin`/`customer_service`/`sales`/`noc`/`teknisi`), jadwal kunjungan, deskripsi masalah, berkas lampiran foto.
  - *Proses*: Sistem mengenerasi `nomor_ticket` (`TCK-YYYY-NNNNNN`), menghitung otomatis `sla_target_selesai` ($Now + \text{SLA Hours}$), menetapkan divisi pada pivot `ticket_divisi`, dan mencatat entri pertama di `ticket_histori`. Jika dibuat oleh staf, notifikasi disiapkan untuk PIC terkait.
  - *Output*: Record tiket tersimpan pada tabel `ticket` dengan status awal `baru`.

#### [RF-26] Transisi Status Tiket & Matriks Otorisasi Peran
- **Deskripsi**: Memperbarui status siklus hidup tiket pekerjaan sesuai batas kewenangan peran masing-masing staf.
- **Aktor / Role**: Dikontrol ketat oleh `TicketPolicy::ubahStatus`.
  - `super_admin` & `admin`: Bebas mengubah ke status apapun (`baru`, `diproses`, `menunggu_konfirmasi`, `selesai`, `batal`).
  - `noc`: Dapat memproses tiket Gangguan/Pemasangan/Pencabutan (`Baru` $\rightarrow$ `Diproses` $\rightarrow$ `Menunggu Konfirmasi` $\rightarrow$ `Selesai` / `Batal`).
  - `teknisi`: Hanya dapat mengubah tiket yang ditugaskan kepadanya dari `Baru` $\rightarrow$ `Diproses` $\rightarrow$ `Menunggu Konfirmasi`.
  - `sales`: Hanya dapat membatalkan (`Batal`) tiket yang didaftarkannya sendiri.
- **Alur Kerja**:
  - *Input*: Status baru, catatan penanganan teknis, flag `is_internal`.
  - *Proses*: Validasi kebijakan otorisasi `ubahStatus` $\rightarrow$ Update status tiket $\rightarrow$ Simpan entri riwayat *immutable* pada `ticket_histori` $\rightarrow$ terapkan efek otomatis (ADR-0041): Pemasangan Selesai/Batal menggerakkan status tahap pemasangan Pelanggan dan Pemasangan Selesai memunculkan aksi Admin membuat Data Registrasi Billing; Pencabutan Selesai mengubah layanan terkait menjadi `berhenti`; Pindah Alamat Selesai memunculkan aksi Admin menerbitkan invoice manual.
  - *Output*: Status tiket terbarui dan audit log histori terekam lengkap.

---

### Modul 13: Geospatial Maps & Estimasi Kabel

#### [RF-27] Peta Sebaran Infrastruktur (*Interactive Network Maps*)
- **Deskripsi**: Menampilkan peta spasial interaktif berisi sebaran titik pelanggan, site instalasi, perumahan, dan tiang ODP.
- **Aktor / Role**: Izin `pelanggan.lihat` via rute `/maps/lokasi`.
- **Alur Kerja**:
  - *Input*: Permintaan layer marker via endpoint `/api/maps/markers` dengan filter kategori (`pelanggan`, `layanan`, `perumahan`, `odp`).
  - *Proses*: Server mengembalikan payload GeoJSON titik koordinat dan status utilitas; Leaflet.js merender *marker clustering* dan *polygon coverage* perumahan.
  - *Output*: Visualisasi peta interaktif dengan popup ringkasan identitas dan kapasitas.

#### [RF-28] Kalkulator Estimasi Kebutuhan Kabel Drop
- **Deskripsi**: Menghitung estimasi panjang kabel optik drop yang dibutuhkan dari koordinat calon pelanggan ke ODP terdekat.
- **Aktor / Role**: Izin `pelanggan.lihat` via `/maps/estimasi-kabel`.
- **Alur Kerja**:
  - *Input*: Titik koordinat latitude & longitude survey/pelanggan, radius pencarian ODP (meter).
  - *Proses*: Query spasial mencari ODP dalam radius menggunakan rumus jarak Haversine / `ST_Distance_Sphere`. Sistem mengaplikasikan formula estimasi:
    $$\text{Estimasi Kabel} = (\text{Jarak Lurus} \times 1.3) + 25\text{ meter (Slack Reserve)}$$
  - *Output*: Daftar rekomendasi ODP terdekat, kapasitas port kosong, estimasi panjang kabel fisik, dan visualisasi garis rute pada peta.

---

### Modul 14: Portal Pelanggan Mandiri (*Customer Self-Service*)

#### [RF-29] Klaim Akun & Autentikasi Pelanggan
- **Deskripsi**: Pelanggan melakukan aktivasi/klaim akun portal mandiri dan login menggunakan guard terpisah.
- **Aktor / Role**: Pelanggan umum (*Guard*: `pelanggan`).
- **Alur Kerja**:
  - *Input*: No. Registrasi (`no_reg`), email terdaftar, nomor HP, dan pembuatan kata sandi baru pada `/portal/klaim-akun`.
  - *Proses*: Verifikasi kecocokan data master `pelanggan` $\rightarrow$ Pembuatan/pembaruan record pada `akun_pelanggan` dengan hash password $\rightarrow$ Login via guard `pelanggan`.
  - *Output*: Pelanggan berhasil masuk ke `/portal/dashboard`.

#### [RF-30] *(Dihapus)* Layanan Tiket Mandiri Pelanggan
- Dihapus permanen; lihat ADR-0040.

---

### Modul 15: Laporan & Dasbor Analitik

#### [RF-31] Dasbor Metrik Operasional & Keuangan
- **Deskripsi**: Menampilkan indikator kinerja utama (KPI) operasional ISP secara *real-time*.
- **Aktor / Role**: Seluruh staf aktif terotentikasi via `/dashboard`.
- **Alur Kerja**:
  - *Input*: Pilihan rentang filter periode waktu.
  - *Proses*: Komponen `Dashboard.php` mengagregasi total pendapatan bulan berjalan, perbandingan tren persentase bulanan (*percentage growth*), rasio penagihan (*Collection Rate*), jumlah pelanggan aktif/prospek/isolir, jumlah layanan expired (H-7 hingga jatuh tempo), antrean tiket terbuka/overdue SLA, dan grafik pendapatan harian sumbu-ganda berbasis ApexCharts.
  - *Output*: Dasbor kartu metrik (`<x-stat-card>`) dan visualisasi grafik analitik interaktif.

#### [RF-32] Laporan Penagihan & Ekspor Data
- **Deskripsi**: Menampilkan rekapitulasi data keuangan penagihan dan memfasilitasi ekspor ke format Microsoft Excel / CSV.
- **Aktor / Role**: Izin `laporan.lihat`, `laporan.ekspor` via `/laporan/billing`.
- **Alur Kerja**:
  - *Input*: Filter status tagihan, tanggal terbit, metode pembayaran, paket layanan, dan wilayah.
  - *Proses*: Agregasi data invoice dan pembayaran; konversi dataset menggunakan library Maatwebsite Excel.
  - *Output*: File unduhan spreadsheet (.xlsx / .csv) laporan keuangan penagihan.

---

### Modul 16: Pengaturan Sistem & Profil Perusahaan

#### [RF-33] Manajemen Profil Perusahaan & Branding Kop Tagihan
- **Deskripsi**: Mengonfigurasi identitas legal entitas ISP, rekening bank penampung, kop surat invoice, tanda tangan resmi, dan logo instansi.
- **Aktor / Role**: Khusus `super_admin` via `/settings/perusahaan`.
- **Alur Kerja**:
  - *Input*: Nama instansi, nama brand, NPWP, alamat kantor, email, telepon, WhatsApp bantuan, nomor rekening bank penerima, nama penandatangan kuitansi, catatan footer invoice, dan unggahan logo/favicon.
  - *Proses*: Memperbarui record `Perusahaan` default (`is_default = true`) dan koleksi media Spatie MediaLibrary. Favicon dinamis disajikan via `/favicon.ico` dan `/favicon.svg`.
  - *Output*: Seluruh header invoice cetak, portal pelanggan, dan identitas aplikasi terbarui secara global.

#### [RF-34] Konfigurasi Gateway Pembayaran Xendit
- **Deskripsi**: Mengatur mode operasi gateway (Sandbox / Production) dan skema pembebanan biaya transaksi (*fee sharing*).
- **Aktor / Role**: Khusus `super_admin` / `peran.lihat` via `/settings/gateway`.
- **Alur Kerja**:
  - *Input*: Nominal fee VA (Rp), persentase fee QRIS (%), flat fee QRIS (Rp), saklar pembebanan fee (`bebankan_ke_pelanggan` vs subsidi ISP), status aktif gateway.
  - *Proses*: Menyimpan konfigurasi pada tabel `pengaturan_gateway`.
  - *Output*: Kalkulasi total pembayaran pada saat pembuatan Xendit Hosted Invoice otomatis menerapkan aturan fee yang disimpan.

---

## 4. Kebutuhan Non-Fungsional (*Non-Functional Requirements*)

### 4.1 Keamanan (*Security*)
- **NFR-SEC-01 (Password Hashing)**: Seluruh kata sandi staf dan pelanggan wajib di-hash menggunakan algoritma standar industri yang aman (Bcrypt / Argon2id) melalui *hashed cast* Laravel.
- **NFR-SEC-02 (Enkripsi Data Sensitif at-Rest)**: Data NIK pelanggan, kata sandi login Router MikroTik, dan kata sandi PPP Secret wajib disimpan dalam format terenkripsi pada basis data menggunakan Laravel Encrypted Cast (AES-256-CBC).
- **NFR-SEC-03 (Proteksi Dokumen Identitas)**: Berkas fisik KTP pelanggan wajib disimpan pada direktori privat terisolasi (disk `local`), tidak dapat diakses publik via symlink web, dan hanya dapat dialirkan (*streamed*) melalui rute berotentikasi dengan pembubuhan *watermark* dinamis (*Nama Staf + Tanggal Akses*) serta pencatatan audit log Spatie.
- **NFR-SEC-04 (Integritas Webhook Gateway)**: Seluruh webhook dari Xendit wajib menyertakan HTTP Header `x-callback-token` yang diverifikasi secara ketat sebelum payload diproses. Percobaan tanpa token yang valid wajib ditolak dengan HTTP Status Code `401 Unauthorized`.
- **NFR-SEC-05 (Proteksi Sesi Impersonasi)**: Selama sesi impersonasi aktif oleh `super_admin`, rute perubahan kata sandi dan pengaturan keamanan sensitif wajib diblokir oleh middleware `impersonate.protect`.

### 4.2 Kinerja & Skalabilitas (*Performance & Scalability*)
- **NFR-PRF-01 (Idempotensi Penagihan & Locking)**: Seluruh operasi finansial (pembuatan invoice, pelunasan pembayaran manual, pemrosesan webhook gateway) wajib dieksekusi di dalam Database Transaction dengan mekanisme *pessimistic row-level locking* (`lockForUpdate`) guna mencegah *race condition* dan pelunasan ganda.
- **NFR-PRF-02 (Provisi Asinkron & Antrean Job)**: Operasi komunikasi socket ke MikroTik dan pengiriman email/notifikasi wajib dapat didelegasikan ke *queue worker* (`php artisan queue:work`) untuk menjaga latensi respons antarmuka pengguna tetap di bawah 200 ms.
- **NFR-PRF-03 (Paginasi & Marker Clustering)**: Tampilan daftar data pada Livewire wajib menggunakan paginasi server-side (10–50 baris per halaman), dan tampilan peta geospasial wajib mengimplementasikan marker clustering untuk menangani hingga ribuan titik tanpa penurunan performa browser client.

### 4.3 Ketersediaan & Keandalan (*Availability & Reliability*)
- **NFR-REL-01 (Auto-Recovery Sinkronisasi Router)**: Sistem wajib menyediakan perintah terjadwal (*scheduled commands*) untuk melakukan *health-check ping* ke router setiap menit dan rekonsiliasi berkala guna memulihkan akun PPP yang hilang akibat *reboot* atau kerusakan konfigurasi pada router fisik.
- **NFR-REL-02 (Audit Trail Immutable)**: Riwayat transisi status tiket (`ticket_histori`) dan log webhook pembayaran (`webhook_log`) bersifat *immutable* (hanya-tambah / *append-only*) dan tidak boleh diedit atau dihapus oleh staf manapun.

### 4.4 Kepatuhan & Standar Kode (*Engineering Standards*)
- **NFR-STD-01 (Gaya Kode)**: Seluruh kode PHP wajib mematuhi standar PSR-12 dan aturan Laravel Pint (`vendor/bin/pint --format agent`).
- **NFR-STD-02 (Analisis Statis & Tipe Data)**: Seluruh model, service, dan komponen wajib lulus inspeksi analisis tipe statis Larastan / PHPStan Level 5+ (`./vendor/bin/phpstan analyse`).
- **NFR-STD-03 (Cakupan Pengujian)**: Seluruh alur kritis (Webhook Xendit, Billing Idempotency, Mutasi Status Tiket, Provisi MikroTik) wajib dilindungi oleh *automated feature test* berbasis Pest PHP.

---

## 5. Matriks Akses Peran vs Fitur (*RBAC Matrix*)

Tabel berikut memetakan matriks kewenangan akses antara masing-masing peran pengguna dengan modul/fitur fungsional sistem:

| Modul / Fitur Sistem | Super Admin | Admin Billing | Sales | NOC | Teknisi | Pelanggan (Portal) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Dasbor Utama / Metrik KPI** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Portal Pelanggan Mandiri** | ✅ *(Impersonate)* | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Kelola Pengguna & Hak Akses Peran** | ✅ | ✅ *(User Saja)* | ❌ | ❌ | ❌ | ❌ |
| **Profil Perusahaan & Konfigurasi Gateway**| ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Audit Activity Log & Impersonasi** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Manajemen Master Wilayah (Kota/Kec/Kel/Perum)** | ✅ | ✅ | ✅ *(Lihat)* | ❌ | ❌ | ❌ |
| **Manajemen Profil Bandwidth** | ✅ | ✅ *(Lihat)* | ❌ | ✅ | ❌ | ❌ |
| **Manajemen Katalog Paket Layanan** | ✅ | ✅ | ✅ *(Lihat)* | ✅ *(Lihat)* | ❌ | ❌ |
| **Manajemen Router MikroTik & Sync** | ✅ | ✅ *(Lihat)* | ❌ | ✅ | ❌ | ❌ |
| **Manajemen IP Pool Subnet** | ✅ | ✅ *(Lihat)* | ❌ | ✅ | ❌ | ❌ |
| **Manajemen ODP & Port Fiber Optik** | ✅ | ✅ *(Lihat)* | ❌ | ✅ | ❌ | ❌ |
| **Peta Jaringan & Estimasi Kabel Spasial** | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Registrasi Pelanggan Baru** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Lihat & Edit Master Pelanggan** | ✅ | ✅ | ✅ *(Milik Sendiri)* | ✅ *(Lihat Saja)* | ✅ *(Lihat Saja)* | ❌ |
| **Lihat Foto KTP (Watermarked)** | ✅ | ✅ | ✅ *(Milik Sendiri)* | ❌ | ❌ | ❌ |
| **Registrasi Layanan / Langganan Baru** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Penerbitan Invoice Tagihan** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Pencatatan Pembayaran Manual / Kasir** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Pembayaran Tagihan Digital (Xendit)** | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| **Manajemen Promo & Diskon** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Buat Tiket Layanan / Gangguan** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Tugaskan PIC Tiket (*Assign PIC*)** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Proses / Update Tiket Lapangan** | ✅ | ✅ | ❌ | ✅ | ✅ *(Ditugaskan)* | ❌ |
| **Batalkan Tiket Kerja** | ✅ | ✅ | ✅ *(Milik Sendiri)* | ✅ | ❌ | ✅ *(Jika Status Baru)* |
| **Laporan Keuangan & Ekspor Spreadsheet**| ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

---
*Dokumen SRS GOBILLING — Selesai Disusun Berdasarkan Kode Sumber Aktual.*
