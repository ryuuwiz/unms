# GOBILLING Domain Context

GOBILLING (formerly UNMS) ISP Network & Billing Management System staff application context and ubiquitous language.


## Agent Documentation & Context Protocol

When exploring, designing, or implementing features across GOBILLING domain models, agents MUST utilize **Laravel Boost** and **Context7** MCP tools to ensure version-accurate syntax and adherence to current package conventions:

### 1. Laravel Boost MCP Tooling Protocol
For Laravel core features, Eloquent operations, first-party packages, and internal database introspection:
- **`search-docs`**: Run scoped, topic-based queries before writing or modifying code:
  - Core Framework: `search-docs(queries=['routing', 'rate limiting', 'events'], packages=['laravel/framework'])`
  - Horizon Queues: `search-docs(queries=['supervisor configuration', 'timeout', 'failed jobs'], packages=['laravel/horizon'])`
  - Roles & Permissions: `search-docs(queries=['assignRole', 'givePermissionTo', 'middleware'], packages=['spatie/laravel-permission'])`
  - Media Management: `search-docs(queries=['conversions', 'collections', 'responsive images'], packages=['spatie/laravel-medialibrary'])`
  - Activity Logging: `search-docs(queries=['logOnlyDirty', 'causedBy', 'attribute_changes'], packages=['spatie/laravel-activitylog'])`
  - Excel Import/Export: `search-docs(queries=['FromQuery', 'WithChunkReading', 'WithBatchInserts'], packages=['maatwebsite/excel'])`
  - Livewire 4 & Flux: `search-docs(queries=['wire:model', 'form validation', 'flux component'], packages=['livewire/livewire', 'livewire/flux'])`
- **`database-schema`**: Inspect actual table schema and indexes before generating migrations, queries, or spatial calculations (key tables: `pelanggan`, `layanan_pelanggan`, `odp`, `odp_ports`, `invoices`, `tickets`).
- **`database-query`**: Execute read-only SELECT queries to verify data during debugging instead of raw tinker commands.
- **`browser-logs`**: Inspect recent client-side errors, Livewire lifecycle failures, or hydration exceptions.
- **`get-absolute-url`**: Resolve canonical URLs (host, scheme, port) when generating or testing links.
- **`record-rule`**: Record durable architectural conventions and discovered traps into `.ai/rules`.

### 2. Context7 MCP Tooling Protocol
For external libraries, cloud services, payment gateway SDKs, container specifications, and styling engines:
- **`resolve-library-id`**: Resolve the exact library identifier (e.g. `resolve-library-id(libraryName='Docker Compose', query='specification')`).
- **`query-docs`**: Query documentation scoped to a specific concept.
- **Canonical Context7 Project Library IDs**:
  - **Docker Compose Spec**: `/docker/compose` (Latest Compose Specification v2+, deploy resources, logging, healthcheck)
  - **FrankenPHP Runtime**: `/dunglas/frankenphp` (Caddyfile directives, worker mode, HTTP/3, compression)
  - **Livewire 4**: `/websites/livewire_laravel_4_x` or `/livewire/livewire` (Full-stack reactivity)
  - **Laravel 13 Framework**: `/websites/laravel_13_x` or `/laravel/docs` (L13 modern features)
  - **Tailwind CSS v4**: `/tailwindlabs/tailwindcss` (Vite `@theme` configuration and utility classes)
  - **Xendit Payment Gateway**: `/xendit/xendit-php` (Invoices, Virtual Accounts, webhook verification)
  - **Spatie Permission**: `/spatie/laravel-permission` (RBAC, role hierarchies, Blade directives)
  - **Spatie MediaLibrary**: `/spatie/laravel-medialibrary` (Media collections & storage)
  - **Spatie Activitylog**: `/spatie/laravel-activitylog` (Audit trail models, log options)
  - **Maatwebsite Excel**: `/maatwebsite/excel` (Spreadsheet handling, chunk imports, queued exports)

### 3. Domain & Ecosystem Reference Matrix

| Domain Area | Key Dependencies | Laravel Boost Tooling | Context7 Library ID | Relevant Skill |
| :--- | :--- | :--- | :--- | :--- |
| **Jaringan & MikroTik** | `evilfreelancer/routeros-api-php`, `laravel/horizon` | `search-docs` (`laravel/horizon`) | `/laravel/horizon` | `configuring-horizon` |
| **Keuangan & Billing** | `xendit/xendit-php`, `barryvdh/laravel-dompdf` | `database-schema` (`invoices`) | `/xendit/xendit-php` | `laravel-best-practices` |
| **Pelanggan & Layanan** | `spatie/laravel-medialibrary`, `spatie/laravel-activitylog` | `search-docs` (`spatie/laravel-medialibrary`, `spatie/laravel-activitylog`) | `/spatie/laravel-medialibrary` | `medialibrary-development` |
| **Otentikasi & Staf** | `laravel/fortify`, `spatie/laravel-permission` | `search-docs` (`spatie/laravel-permission`) | `/spatie/laravel-permission` | `fortify-development`, `laravel-permission-development` |
| **Portal & Reaktivitas** | `livewire/livewire`, `livewire/flux`, Tailwind v4 | `search-docs` (`livewire/livewire`, `livewire/flux`) | `/websites/livewire_laravel_4_x` | `livewire-development`, `fluxui-development` |
| **Maps & Estimasi Kabel**| MySQL 8.4 Spatial GIS, Leaflet.js | `database-schema` (`odp`, `odp_ports`), `database-query` | `/docker/compose` | `domain-modeling` |
| **Laporan & Ekspor** | `maatwebsite/excel` | `search-docs` (`maatwebsite/excel`) | `/maatwebsite/excel` | `laravel-excel` |
| **Kontainer Produksi** | FrankenPHP 8.4, Caddy, Dokploy, Traefik | `get-absolute-url`, env config | `/docker/compose` | `docker-expert` |

## Language

**Dashboard**:
Halaman beranda utama ringkasan metrik operasional dan status sistem yang dapat diakses oleh semua staff aktif.
_Avoid_: Home, Beranda Bebas, Main View

**Navigasi Sekunder**:
Bilah navigasi horizontal kontekstual di bagian atas layar (Secondary Header) yang menyajikan tab sub-menu modul aktif atau aksi spesifik halaman.
_Avoid_: Top Menu Bebas, Double Header, Tab Lepas

**Pelanggan & Layanan**:
Kelompok navigasi utama untuk operasional data pelanggan, langganan layanan internet aktif, katalog paket, dan konfigurasi profil bandwidth.
_Avoid_: Menu Utama, Main Navigation, CRM, Pelanggan Saja

**Keuangan & Billing**:
Kelompok navigasi untuk manajemen tagihan invoice, penerimaan pembayaran, promo, dan laporan keuangan.
_Avoid_: Finance, Kasir, Akuntansi

**Jaringan & Infrastruktur**:
Kelompok navigasi untuk konfigurasi teknis perangkat MikroTik RouterOS dan distribusi alokasi subnet IP Pool.
_Avoid_: Network, Hardware, Alat Jaringan

**Area & Wilayah**:
Kelompok navigasi untuk manajemen hierarki cakupan lokasi operasional ISP dari tingkat Kota, Kecamatan, Kelurahan, hingga Perumahan/Cluster.
_Avoid_: Lokasi, Mapping, Coverage Area

**Maps & Estimasi Kabel**:
Kelompok navigasi geospasial untuk visualisasi peta sebaran infrastruktur (Maps Lokasi) dan kalkulator perhitungan kebutuhan kabel optik (Estimasi Kabel).
_Avoid_: GIS Saja, Peta Bebas, Kalkulator Saja


**Administrasi**:
Kelompok navigasi untuk manajemen sistem, konfigurasi peran, dan kontrol hak akses pengguna.
_Avoid_: Administration, Settings, Pengaturan

**Pengguna**:
Entitas akun staff internal yang memiliki akses login dan hak akses ke sistem NMS.
_Avoid_: User, Staff Account, Member

**Peran**:
Kumpulan izin (permissions) yang diberikan kepada pengguna untuk membatasi akses fitur tertentu.
_Technical Reference_: Spatie Permission (`spatie/laravel-permission`), Laravel Boost: `search-docs(packages=['spatie/laravel-permission'])`, Context7: `/spatie/laravel-permission`.
_Avoid_: Role, Group, Level

**Pelanggan**:
Entitas master data konsumen/klien ISP yang mencakup identitas kontak dan lokasi fisik pemasangan jaringan.
_Avoid_: Client, Customer Account, Member

**No. Registrasi**:
Pengenal unik untuk setiap pelanggan dengan format default `[Prefix][DDMMYYYY][Counter]` (contoh: `BF2309202601`, `ARS2309202601`, `WG2309202601`), dapat di-custom saat pendaftaran pelanggan, dan dijamin unik di seluruh sistem.
_Avoid_: Customer ID, Nomor Pelanggan, No Langganan, CUST-XXXXXX, No Reg Duplikat

**Format Identitas Pelanggan**:
Format standar representasi identitas pelanggan untuk antarmuka staf backoffice dan selector sistem dengan susunan `[No. Reg]_[Nama Pelanggan]` (contoh: `WG2309202601_Budi Santoso`). Pada dropdown selector dilengkapi informasi sekunder di dalam kurung: `WG2309202601_Budi Santoso (0812xxxx • Cluster Melati)`.
_Avoid_: Nama Saja Tanpa No Reg, No Reg Tanpa Nama, Format Strip Tak Beraturan (Gunakan Format Baku `No. Reg_Nama`)

**Data Registrasi Billing**:
Entitas registrasi langganan billing aktif (sebelumnya disebut Layanan Pelanggan, merujuk pada `docs/data_unms.md` bagian `# Layanan & Network -> ## Billing`) yang menghubungkan seorang pelanggan dengan paket layanan internet tertentu, router gateway, alokasi IP Pool / IP Statis, kredensial PPP, Site ID, dan masa aktif. Setiap penambahan divalidasi anti-duplikasi pada router & paket yang sama saat status masih aktif/proses/suspend, dengan tetap mendukung multi-site per pelanggan.
_Avoid_: Subscription, Akun Internet, Koneksi, Layanan Saja

**PPP Username Credential**:
Identitas autentikasi PPPoE pelanggan di RouterOS dengan format `{No.Reg}_{NNNNN}` (contoh: `BF2308202601_84920`) — prefix adalah No.Reg pelanggan, suffix adalah 5-digit angka acak (*CSPRNG token* `10000`–`99999`) yang dijamin unik global di tabel `layanan_pelanggan`. Di-generate otomatis oleh sistem saat layanan dibuat; staff dapat override asal format dipatuhi. Disimpan di kolom `ppp_username` tabel `layanan_pelanggan`.
_Avoid_: Username Bebas, PPP User Manual, Format Lama (`user_budi_01`)

**Site ID**:
Pengenal unik titik instalasi layanan pelanggan (format `SITE-XXXXXXXX`).
_Avoid_: Service ID, Lokasi ID

**Paket Layanan**:
Entitas katalog paket internet ISP yang menentukan profil bandwidth, tarif, dan masa aktif (hari/bulan).
_Avoid_: Package, Product, Paket Data

**Profil Bandwidth**:
Konfigurasi limit kecepatan transfer data (max limit upload/download, burst rate, priority) yang diinput dan disimpan dalam satuan standar Mbps, lalu otomatis dikonversi ke format numerik bits per second (bps) saat di-provision ke MikroTik RouterOS.
_Avoid_: Speed Profile, Paket Bandwidth, Konfigurasi Kbps (Gunakan Mbps)

**Konversi Bandwidth Biner (Mbps ke bps)**:
Mekanisme konversi otomatis kecepatan data dari satuan input Mbps ke nilai numerik bits per second (bps) menggunakan formula biner ($1\text{ Mbps} = 1024 \times 1024 = 1.048.576\text{ bps}$) guna memberikan buffer kompensasi overhead paket (PPPoE/TCP/IP) agar pengujian kecepatan riil pelanggan tepat sasaran.
_Avoid_: Konversi Desimal Mentah (1.000.000 bps Tanpa Buffer Overhead), Input Manual bps di Form

**Router**:
Entitas perangkat MikroTik RouterOS sebagai pengendali layanan dan bandwidth, diakses melalui RouterOS API (port 8728).
_Avoid_: Switch, Gateway Umum

**IP Pool**:
Blok alokasi alamat IP (Network, CIDR, Range IP) yang terikat pada Router untuk distribusi IP pelanggan.
_Avoid_: Subnet Bebas, DHCP Range

**Antrean Prioritas MikroTik (`mikrotik-high`)**:
Antrean antarmuka Horizon khusus untuk provisi instan dan siklus hidup pelanggan real-time (tambah akun, ubah paket, isolir, buka isolir) dengan alokasi proses worker terisolasi tanpa jeda.
_Technical Reference_: Laravel Horizon (`laravel/horizon`), Laravel Boost: `search-docs(packages=['laravel/horizon'])`, Context7: `/laravel/horizon`.
_Avoid_: Antrean Campur, Single Queue Mikrotik, Mikrotik Low Saja

**Antrean Background MikroTik (`mikrotik-low`)**:
Antrean antarmuka Horizon untuk pemeliharaan berkala berulang (health check ping, validasi massal PPP secret, sinkronisasi pool/profil) dengan mekanisme non-blocking dan auto-scaling.
_Technical Reference_: Laravel Horizon (`laravel/horizon`), Laravel Boost: `search-docs(packages=['laravel/horizon'])`, Context7: `/laravel/horizon`.
_Avoid_: Background Queue Bebas, Single Queue Mikrotik

**Rekonsiliasi Cepat PPP In-Memory**:
Strategi sinkronisasi berkala PPP Secret di RouterOS yang membaca seluruh data dalam 1x bulk query, membandingkan data di RAM PHP (0 ms), dan hanya mengirim perintah mutasi secara targeted pada entri yang tidak sinkron tanpa query baca berulang per akun.
_Avoid_: Query Nested Per Akun, Loop Sinkron Monolitik, Reconcile Lambat

**Kota**:
Entitas tingkat administratif kota/kabupaten dalam cakupan operasional ISP.
_Avoid_: City, Daerah

**Kecamatan**:
Entitas tingkat administratif kecamatan di bawah kota/kabupaten.
_Avoid_: District, Wilayah 2

**Kelurahan**:
Entitas tingkat administratif kelurahan/desa di bawah kecamatan.
_Avoid_: Sub-district, Desa

**Perumahan**:
Entitas cluster, perumahan, atau kawasan pemukiman spesifik titik pemasangan instalasi pelanggan.
_Avoid_: Residential, Cluster, Komplek

**Invoice**:
Dokumen tagihan pembayaran resmi atas layanan internet pelanggan dengan format penomoran yang menyertakan No. Registrasi pelanggan: `INV-[No.Reg]-[YYYYMM]-[Counter]` (contoh: `INV-BF2309202601-202608-01`).
_Avoid_: Tagihan Bebas, Kuitansi (sebelum dibayar), Bill, Invoice Tanpa No Reg

**Periode Tagihan**:
Identitas siklus bulan penagihan layanan (format `YYYY-MM`) yang memetakan kewajiban bayar langganan untuk satu siklus masa aktif dan menjamin batas 1 tagihan per layanan per siklus.
_Avoid_: Bulan Tagih Bebas, Periode Manual, Cycle ID

**Pembatalan Invoice**:
Tindakan perubahan status invoice menjadi `dibatalkan` oleh sistem atau staf berwenang yang menggugurkan kewajiban bayar tanpa menghapus riwayat audit trail (misal akibat koreksi tagihan ganda atau perubahan paket).
_Avoid_: Hapus Tagihan Manual, Void Bebas, Delete Invoice

**Pembayaran**:
Catatan transaksi penerimaan dana atas sebuah invoice yang memicu perpanjangan masa aktif layanan secara otomatis.
_Avoid_: Transaksi Kasar, Setoran

**Promo**:
Program diskon (nominal / persentase) atau bonus durasi yang dapat diaplikasikan pada penerbitan invoice.
_Avoid_: Voucher Bebas, Potongan Informal

**Portal Pelanggan**:
Antarmuka web mandiri untuk pelanggan internet ISP guna melihat informasi tagihan aktif, riwayat transaksi, profil langganan, dan melakukan pembayaran secara real-time.
_Avoid_: Client Area Bebas, Customer App Terpisah, Halaman Member

**Akun Pelanggan**:
Entitas kredensial autentikasi pengguna portal (guard `pelanggan`) yang terikat 1-to-1 dengan master data Pelanggan.
_Avoid_: User Pelanggan, Akun Web Bebas

**Transaksi Payment Gateway**:
Catatan transaksi penerbitan tagihan digital ke payment gateway (seperti Xendit, iPaymu) dengan identitas `external_id` unik untuk penjaminan idempotensi dan riwayat sesi pembayaran.
_Technical Reference_: Xendit PHP SDK (`xendit/xendit-php`), Context7: `/xendit/xendit-php`.
_Avoid_: Billing Gateway, Tagihan Xendit Saja, Order ID Bebas

**Link Pembayaran Gateway**:
Tautan resmi sesi pembayaran terkelola dari payment gateway aktif (`payment_gateway_url`) yang memuat pilihan metode bayar secara langsung di halaman hosted gateway tanpa form custom internal.
_Avoid_: Custom Checkout URL, Link Bayar Bebas, Xendit URL Saja

**Log Webhook**:
Catatan audit trail penerimaan callback HTTP dari payment gateway untuk mencatat event id, payload mentah, status verifikasi signature/token, dan proses eksekusi database.
_Avoid_: Callback History, Webhook Record, Xendit Webhook Saja

**Koneksi Payment Gateway**:
Entitas konfigurasi akun penyedia gateway pembayaran (seperti Xendit, iPaymu) yang memuat kredensial terenkripsi di database, status aktif, mode sandbox, dan penanda default gateway.
_Avoid_: Akun Gateway Bebas, Setting Gateway Statis, Env Gateway

**Driver Payment Gateway**:
Komponen adapter perangkat lunak yang mengimplementasikan protokol komunikasi API dan verifikasi signature spesifik dari masing-masing penyedia gateway (seperti `XenditDriver`, `IpaymuDriver`).
_Avoid_: Payment Plugin, Modul Bayar Bebas

**Biaya Admin Gateway**:
Biaya pemrosesan transaksi dari penyedia payment gateway (default riset: VA Rp 4.000, QRIS 0.70%) yang secara default dibebankan kepada pelanggan (`bebankan_ke_pelanggan: true`) dengan penambahan nominal tagihan (Xendit `fees`) atau direct charge gateway (iPaymu `feeDirection: 'BUYER'`).
_Avoid_: Biaya Tambahan Bebas, Hidden Fee, Potongan ISP Saja

**Impersonasi**:
Aksi staf dengan peran `super_admin` untuk masuk sementara (*login as*) ke sesi pengguna staf lain atau akun portal pelanggan tanpa membutuhkan kata sandi untuk tujuan *troubleshooting*, audit hak akses, dan verifikasi tampilan portal secara *real-time*.
_Avoid_: Ghost Login, Bypass Auth, Switch User Bebas

**Tiket & Operasional**:
Kelompok navigasi untuk manajemen tiket layanan, penanganan aduan gangguan jaringan, dan pelacakan pekerjaan teknis lapangan.
_Avoid_: Support Desk, Helpdesk Umum, Tugas Lapangan

**Tiket**:
Entitas berkas kerja permohonan layanan atau penanganan masalah teknis (Pemasangan, Gangguan, Pencabutan, Pindah Alamat) dengan status siklus hidup dan penomoran otomatis terpusat. Dapat dibuat oleh staf (sumber: `manual`/`sistem`) maupun pelanggan dari Portal Pelanggan (sumber: `portal`).
_Avoid_: Issue, Aduan Bebas, Task, Case

**Nomor Tiket**:
Pengenal unik resmi untuk setiap tiket yang di-generate sistem secara terstandarisasi dengan format `TCK-YYYY-NNNNNN`.
_Avoid_: Ticket ID Bebas, No Aduan, Kode Masalah

**Histori Tiket**:
Catatan log kronologis *immutable* (hanya-baca) yang merekam setiap transisi status, pergantian PIC, dan catatan penanganan teknis.
_Technical Reference_: Spatie Activitylog (`spatie/laravel-activitylog`), Laravel Boost: `search-docs(packages=['spatie/laravel-activitylog'])`, Context7: `/spatie/laravel-activitylog`.
_Avoid_: Riwayat Bebas, Log Tiket Manual, Catatan Lepas

**PIC (Person in Charge)**:
Staf pengguna internal (User) yang ditugaskan secara formal untuk bertanggung jawab menyelesaikan suatu tiket.
_Avoid_: Assignee, Petugas Lapangan Bebas, Pelaksana

**Divisi Tiket**:
Satu atau lebih divisi internal (Admin, Customer Service, Sales, NOC, Teknisi) yang bertanggung jawab menangani sebuah tiket, disimpan dalam relasi many-to-many via pivot table `ticket_divisi`. Tiket portal Gangguan otomatis ditugaskan ke [NOC, Teknisi] untuk mendukung koordinasi penjadwalan; Pencabutan dan Pindah Alamat ke [Teknisi].
_Avoid_: Divisi Tunggal per Tiket, Single Enum Divisi

**Tiket Portal Pelanggan**:
Tiket yang dibuat oleh pelanggan secara mandiri melalui Portal Pelanggan dengan jenis terbatas (Gangguan, Pencabutan, Pindah Alamat — Pemasangan dikecualikan). Sistem otomatis menetapkan prioritas dan divisi berdasarkan jenis; `sumber = portal`, `dibuat_oleh = null`. Pelanggan dapat membatalkan tiket selama status masih `Baru` dengan alasan wajib yang dicatat di Histori Tiket.
_Avoid_: Tiket Staf yang Dibuat Atas Nama Pelanggan, Tiket Manual dengan Source Portal

**Catatan Internal Tiket**:
Entri Histori Tiket yang ditandai `is_internal = true` — hanya terlihat oleh staf dan tersembunyi dari tampilan Portal Pelanggan. Catatan tanpa flag (`is_internal = false`, default) bersifat publik dan tampil di timeline histori portal pelanggan.
_Avoid_: Menyembunyikan Semua Histori dari Pelanggan, Menampilkan Catatan Teknis Mentah Tanpa Filter

**Notifikasi Portal Pelanggan**:
Sistem notifikasi in-app berbasis Laravel Database Notifications untuk Akun Pelanggan di Portal Pelanggan, ditampilkan melalui ikon bell di navbar portal. Dipicu oleh setiap perubahan status tiket milik pelanggan. Auto-ditandai dibaca saat pelanggan membuka halaman detail tiket terkait; tombol "Tandai Semua Dibaca" tersedia. Dirancang untuk dapat diperluas ke kanal WhatsApp (WAHA API) di masa mendatang.
_Avoid_: Email Notification Portal, Push Notification Terpisah, Polling Manual Tanpa Bell Icon

**Target SLA**:
Batas waktu tenggat penyelesaian tiket yang dihitung otomatis berdasarkan skala prioritas saat tiket pertama kali dibuat.
_Avoid_: Deadline Bebas, Target Waktu, Estimasi Jam

**Profil Perusahaan**:
Entitas identitas legal, brand bisnis, alamat operasional, kontak bantuan, nomor NPWP, rekening bank penerima, dan catatan resmi yang dikonfigurasi untuk kop tagihan (invoice header), kuitansi digital, dan antarmuka portal pelanggan.
_Avoid_: Company Setting Bebas, Header Manual, Info PT Lepas

**Kesiapan Multi-Tenant (Multi-Tenant Readiness)**:
Arsitektur pemisahan entitas data organisasi/perusahaan yang dirancang dengan skema relasional `perusahaan_id` (record default `is_default=true` untuk mode internal) sehingga dapat dinaikkan menjadi platform SaaS multi-penyewa di masa depan tanpa mengubah model domain inti.
_Avoid_: Hardcoded Single Company, Multi Database Terpisah Tanpa Pola

**Berkas Media (Media Library)**:
Pengelolaan berkas digital (logo instansi, foto identitas/KTP, foto dokumentasi teknis tiket, dan bukti transfer pembayaran) yang terpusat melalui relasi polimorfik Spatie MediaLibrary dengan penanganan otomatis konversi gambar, mime checking, dan siklus hidup berkas.
_Technical Reference_: Spatie MediaLibrary (`spatie/laravel-medialibrary`), Laravel Boost: `search-docs(packages=['spatie/laravel-medialibrary'])`, Context7: `/spatie/laravel-medialibrary`.
_Avoid_: File Path Manual Bebas, Upload Lepas Tanpa Relasi

**Kartu Metrik (Stat Card)**:
Komponen visual modular (`<x-stat-card>`) untuk menampilkan ringkasan indikator performa utama (KPI) yang dilengkapi dengan tren perbandingan persentase periode, badge status, ikon bernuansa tematik, dan tautan navigasi kontekstual.
_Avoid_: Box Angka Bebas, Card Mentah, Stat Lepas

**Grafik Analitik (Chart Component)**:
Komponen visualisasi data interaktif berbasis ApexCharts yang terintegrasi dengan Alpine.js dan Livewire 4, mendukung tema dark-mode otomatis untuk menampilkan tren pendapatan 12-bulan, proporsi paket layanan, dan beban tiket operasional.
_Technical Reference_: Livewire 4 (`livewire/livewire`) & Flux UI (`livewire/flux`), Laravel Boost: `search-docs(packages=['livewire/livewire', 'livewire/flux'])`, Context7: `/websites/livewire_laravel_4_x`.
_Avoid_: Gambar Grafik Statis, Chart Canvas Tanpa Reaktivitas

**Collection Rate**:
Rasio efektivitas penagihan dalam persentase yang dihitung dari perbandingan nominal tagihan lunas terhadap total nominal tagihan yang diterbitkan pada periode tertentu.
_Avoid_: Persen Bayar Bebas, Efektivitas Kas

**Layanan Expired**:
Layanan pelanggan yang telah melewati tanggal jatuh tempo masa aktif paket (`tanggal_expired <= now()`) atau berada dalam masa tenggang menjelang jatuh tempo (H-7) dan belum dilakukan pelunasan tagihan perpanjangan.
_Avoid_: Member Hangus, Langganan Mati, Akun Basi

**Tren Pendapatan Harian**:
Visualisasi grafik sumbu-ganda (*dual-axis*) harian selama bulan berjalan yang memadukan kurva nominal pendapatan (Rp) pada sumbu primer dan jumlah volume transaksi berhasil pada sumbu sekunder.
_Avoid_: Grafik Omzet Harian Lepas, Chart Transaksi Terpisah

**Provisi Router Otomatis (Automatic Router Provisioning)**:
Pipeline orkestrasi berurutan yang menyelaraskan seluruh data konfigurasi master UNMS ke perangkat MikroTik RouterOS melalui RouterOS API (pemeriksaan koneksi & resource $\rightarrow$ IP Pool & Simple Queue $\rightarrow$ Profil Bandwidth biner $\rightarrow$ PPP Secret Layanan Pelanggan $\rightarrow$ sinkronisasi status isolir dan pemutusan sesi aktif).
_Avoid_: Sync Parsial Tanpa Urutan, Push Manual Bebas, Provisi Terfragmentasi

**Rekonsiliasi Router (Router Reconciliation)**:
Proses komparasi periodik terjadwal dan auto-recovery antara basis data UNMS dengan konfigurasi aktual di RouterOS untuk mendeteksi *configuration drift*, memulihkan PPP secret/profil yang hilang atau terhapus di router, dan menyelaraskan status disabled.
_Avoid_: Cek Status Lepas, Sync Buta, Ping Tanpa Rekonsiliasi

**Orphaned Secret (PPP Secret Tak Terkelola)**:
Akun PPP Secret yang terdeteksi ada di RouterOS namun tidak memiliki rekaman aktif di database UNMS (misal akun manual sisa instalasi lama atau layanan yang telah dihapus). Ditangani secara audit-safe (hanya dicatat) dan dapat dibersihkan dengan opsi flags eksplisit.
_Avoid_: Hapus Buta Akun Router, Rogue User Tanpa Log

**IP Pool & Router Gateway Layanan**:
Binding eksplisit antara satu Layanan Pelanggan (Data Registrasi Billing), Router Gateway (`router_id`), dan IP Pool (`ip_pool_id`) yang secara ketat menjamin alokasi IP Pool berasal dari router yang sama. Menentukan nilai `local-address` (IP gateway pool) dan `remote-address` (nama pool IP untuk PPPoE dinamis atau IP statis literal) pada akun PPP Secret di RouterOS. Jika sistem hanya memiliki 1 Router Online aktif, Livewire secara otomatis memilih router tersebut (*single-router auto-selection*), dan jika router tersebut hanya memiliki 1 IP Pool aktif, sistem secara otomatis memilih pool tersebut (*single-pool auto-selection*).
_Avoid_: Remote Address Kosong, IP Pool Implisit dari Profile Saja, Cross-Router IP Pool Selection, Visual Desync Dropdown Tanpa State Livewire

**Alokasi IP Statis Layanan**:
Pengalokasian alamat IPv4 statis dedicated (kolom `ip_static` di `layanan_pelanggan`) untuk pelanggan dengan jenis koneksi IP Static yang langsung diset sebagai `remote-address` pada konfigurasi PPP Secret MikroTik.
_Avoid_: IP Statis Tanpa Validasi IPv4, Alokasi Statis Tak Tercatat di Database

**Cleanup PPP Secret Lama (Router Migration Cleanup)**:
Proses otomatis yang dipicu oleh Observer saat `router_id` sebuah Layanan Pelanggan berubah (migrasi router). Sistem menghapus PPP Secret dari router lama via job antrian (`CleanupPppSecretOnOldRouterJob`) agar tidak terjadi duplikat secret lintas router. Jika router lama offline, kegagalan dicatat di `MikrotikJobLog` sebagai Failed untuk tindak lanjut manual.
_Avoid_: Biarkan Secret Lama, Hapus Manual, Duplikat Dibiarkan Sampai Rekonsiliasi

**Multi-Layanan Pelanggan (Granular Billing & Isolation)**:
Dukungan di mana satu pelanggan (entitas `Pelanggan`) dapat memiliki banyak layanan internet aktif (entitas `LayananPelanggan` 1:N). Setiap layanan memiliki `site_id` dan `ppp_username` berurutan unik (`{no_reg}_00001`, `{no_reg}_00002`), paket layanan independen, serta siklus invoice tersendiri (`Invoice` 1:1 per periode per layanan). Jika salah satu layanan menunggak (Suspend), isolir dilakukan secara parsial hanya pada PPP Secret layanan yang bersangkutan tanpa mengganggu layanan lain milik pelanggan yang sama.
_Avoid_: Akumulasi Invoice Tanpa Rincian Site, Isolir Global Seluruh Layanan Pelanggan, Duplikasi Pelanggan untuk Multi-Lokasi

**Segmentasi Jalur IP Pool (Up To vs Dedicated)**:
Pemisahan jalur alokasi IP, gateway, dan hierarki prioritas antrean jaringan MikroTik (QoS) menjadi 3 tingkatan segmen:
1. **Residensial Up To (Broadband / Shared)**: Terikat ke `Pool-Rumah` (`10.0.0.0/24`, gateway `10.0.0.1`, queue priority 7–8) dengan burst rate & rasio contention.
2. **Residensial 1:1 (Dedicated Home / Gamer / Streamer)**: Terikat ke `Pool-Rumah` (`10.0.0.0/24`, gateway `10.0.0.1`, queue priority 3–5) dengan flat 1:1 CIR tanpa pembagian bandwidth.
3. **Bisnis / Enterprise 1:1 (Corporate Dedicated)**: Terikat ke `Pool-Bisnis` (`10.0.1.0/24`, gateway `10.0.1.1`, queue priority 1–2) atau IP Statis dedicated (`10.0.1.X`) dengan SLA tinggi.
_Avoid_: Pencampuran Subnet Up To dan Dedicated, Single Pool untuk Semua Kelas Layanan, Gateway Ambigu, Pengabaian Segmen Residensial 1:1

**Manajemen Local & Remote Address PPP Secret**:
Penetapan eksplisit parameter `local-address` (IP gateway host pertama dari IP Pool terkait pada Router pelanggan) dan `remote-address` (nama IP Pool untuk PPPoE dinamis atau IP statis literal) pada setiap akun PPP Secret di RouterOS oleh UNMS. Dilengkapi mekanisme *auto-ensure* IP Pool sebelum pembuatan secret, validasi relasi router ketat (*scoped validation*), dan rekonsiliasi periodik dengan kemampuan *auto-healing*.
_Avoid_: Local Address Kosong di Secret, Remote Address Kosong untuk Dynamic Client, Ketergantungan Profile Default RouterOS, Provisi PPPoE Tanpa IP Pool

**ODP (Optical Distribution Point)**:
Titik terminasi fisik kabel distribusi serat optik luar ruang tempat tersambungnya kabel drop instalasi pelanggan, memiliki kapasitas port terukur (4, 8, 16, 24, 32), deskripsi/PON, dan koordinat geografis presisi untuk perhitungan jalur pemasangan jaringan.
_Avoid_: Box ODP Bebas, Kotak Fiber Lepas, ODP Tanpa Koordinat

**Port ODP**:
Slot fisik terminasi pada perangkat ODP yang melacak status pemakaian (*kosong, terpakai, rusak*) dan terikat 1:1 dengan satu entitas Layanan Pelanggan (Data Registrasi Billing).
_Avoid_: Colokan Kabel, Slot ODP Lepas

**Peta Jaringan (Data Maps)**:
Antarmuka visualisasi spasial interaktif berbasis Leaflet.js dan OpenStreetMap yang memetakan persebaran 4 layer entitas (Pelanggan, Layanan/Site, Perumahan, ODP) dengan dukungan toggle filter layer independen dan marker clustering untuk menangani ribuan titik secara ringan dan responsif.
_Avoid_: Google Maps API Berbayar, Peta Statis Gambar, Peta Tanpa Clustering

**Estimasi Kabel**:
Kalkulator geospasial non-destruktif (*transient calculator*) untuk mencari kandidat ODP terdekat dari titik koordinat survey/pelanggan dan menghitung estimasi panjang kabel drop fisik yang dibutuhkan menggunakan kombinasi formula spasial MySQL `ST_Distance_Sphere` dan faktor koreksi lapangan.
_Technical Reference_: MySQL 8.4 Spatial GIS (`ST_Distance_Sphere`), Laravel Boost: `database-schema` (`odp`, `odp_ports`), `database-query`.
_Avoid_: Jarak Udara Mentah Tanpa Slack, Routing Pathfinding Berat, Kalkulator Tersimpan Permanen

**Faktor Pengali Kabel**:
Parameter pengali estimasi panjang kabel fisik (default: `1.3`) terhadap jarak lurus geografis (*Haversine*) untuk mengompensasi jalur tiang listrik, belokan jalan, penurunan tiang, dan rute fisik di lapangan.
_Avoid_: Jarak Euclidean 1:1, Perhitungan Tanpa Faktor Rute

**Cadangan Kabel (Slack Reserve)**:
Tambahan panjang kabel fisik dalam satuan meter (default: `25m`) yang dialokasikan untuk sambungan terminasi (*splicing*), gulungan cadangan di tiang (*slack loop*), dan penurunan kabel ke roset/ONT pelanggan.
_Avoid_: Kabel Pas-Pasan Tanpa Cadangan, Estimasi Tanpa Slack

**Import ODP Geospasial (KML & GeoJSON)**:
Fasilitas unggah dan konversi berkas geospasial standar industri (.kml dari Google Earth atau .geojson dari QGIS/CAD) untuk mengekstraksi titik koordinat ODP, nama, dan deskripsi secara massal dengan tinjauan data (*preview table*) sebelum disimpan dan di-generate port-nya secara otomatis.
_Technical Reference_: Maatwebsite Excel (`maatwebsite/excel`), Laravel Boost: `search-docs(packages=['maatwebsite/excel'])`, Context7: `/maatwebsite/excel`.
_Avoid_: Input Manual Satu Per Satu untuk Proyek Baru, Format CSV Polos Saja

**Layer Coverage GeoJSON (Polygon Cakupan)**:
Fitur visualisasi batas area cakupan jaringan (coverage boundary / polygon) pada Data Maps berbasis format GeoJSON poligon, memungkinkan tim membedakan zona ter-cover dan area blank-spot secara visual.
_Avoid_: Polygon Hardcoded, Gambar Overlay Statis

**Dokumen Pelanggan Terenkripsi**:
Pengelolaan berkas identitas (KTP) dan dokumen legalitas pelanggan (MOU, kontrak langganan, formulir pendaftaran) yang disimpan secara terenkripsi at-rest pada disk privat terisolasi, diakses melalui endpoint berotentikasi dengan audit log Spatie Activitylog, dan dilindungi watermark dinamis untuk kepatuhan UU Pelindungan Data Pribadi (UU PDP).
_Avoid_: Dokumen Publik Tak Terenkripsi, Simpan KTP di Folder Public, Berkas Tanpa Audit Trail

**Watermark Dokumen Identitas**:
Penyematan teks tanda air dinamis on-the-fly pada saat peninjauan/unduh berkas identitas pelanggan (memuat nama staf pengakses dan timestamp verifikasi) untuk mencegah kebocoran atau penyalahgunaan tangkapan layar dokumen identitas secara internal.
_Avoid_: Peninjauan Gambar Mentah Tanpa Watermark, Hardcoded Watermark Statis

**Queue Webhook Payment**:
Antrian latar belakang terisolasi berbasis Laravel Queue (`ProcessPaymentWebhookJob`) yang memproses callback status pembayaran gateway secara asinkron pasca commit HTTP acknowledgment (HTTP 200) untuk menjamin pemenuhan SLA gateway dan ketahanan retry.
_Avoid_: Synchronous Webhook Processing, Long-Running Callback Handler

**Validasi Ketat Nominal Gateway**:
Mekanisme verifikasi integritas nominal bayar integer IDR tanpa toleransi selisih (`paid_amount === total_tagihan`) sebelum pelunasan invoice dan perpanjangan layanan internet dieksekusi, mencegah anomali *underpayment* atau *overpayment*.
_Avoid_: Loose Amount Verification, Auto-Pay Tanpa Verifikasi Nominal

**Perintah Instalasi Produksi (Production Setup Command)**:
Perintah Artisan resmi (`php artisan app:install` / `app:setup-production`) yang memfasilitasi migrasi database, symlink storage, injeksi master data produksi, serta provisioning akun `super_admin` awal secara interaktif maupun non-interaktif (*CI/CD headless mode*).
_Avoid_: Manual User Registration di Database, Hardcoded Admin Password, Setup Script Lepas

**Seeder Produksi (Production Seeder)**:
Bundel seeder data master esensial (`ProductionSeeder`) yang mencakup RBAC Permissions & Roles (`RolesAndPermissionsSeeder`), Profil Perusahaan Default (`PerusahaanSeeder`), Template Notifikasi WhatsApp (`WaTemplateSeeder`), dan Aturan Pengingat Jatuh Tempo (`AturanPengingatTagihanSeeder`) tanpa menyertakan data uji coba (mock users/pelanggan fiktif).
_Avoid_: Full db:seed di Produksi, Pencampuran Dummy Data dengan Master RBAC

**WAHA Gateway**:
Infrastruktur layanan WhatsApp HTTP API (WhatsApp HTTP API v2026.8.1) terpadu sebagai penyedia gateway pesan utama untuk notifikasi tagihan billing, pesan broadcast massal, dan konfirmasi pembayaran.
_Technical Reference_: WhatsApp HTTP API (`devlikeapro/waha`), Internal Docker Compose service `waha`.
_Avoid_: Gateway Pihak Ketiga Tak Terkelola, Wablas Saja

**WAHA Session Pairing**:
Mekanisme otentikasi dan penautan akun WhatsApp Web staf secara langsung melalui modal pemindaian QR Code di antarmuka portal backoffice GOBILLING dengan pendeteksian status sesi real-time (`WORKING`, `SCAN_QR_CODE`, `STOPPED`).
_Avoid_: Login Manual ke Swagger Eksternal, Hardcoded Single Session

**DLR Message Ack Tracking**:
Pelacakan siklus hidup pengiriman pesan keluar WhatsApp berbasis webhook `message.ack` secara granular (`Menunggu` $\rightarrow$ `Terkirim/Server` $\rightarrow$ `Tersampaikan/Device` $\rightarrow$ `Dibaca/Read` $\rightarrow$ `Gagal`) yang dicatat pada tabel antrian blast.
_Avoid_: Blind Blast Tanpa Tracking, Status Sent Statis

**Pengingat Tagihan Otomatis**:
Sistem pengingat tagihan terjadwal (`invoice:kirim-pengingat`) yang berjalan setiap jam untuk mengevaluasi aturan pengingat aktif dan mengirimkan notifikasi berformat template dinamis dengan link pembayaran ke dua kanal sekaligus: WhatsApp (antrean `wa-blast` Horizon dengan perlindungan pembatasan laju) dan Email (notification queue standar, langsung ke `Pelanggan.email` jika terisi).
_Avoid_: Pengiriman Manual Satu Per Satu, Blast Tanpa Antrean Terisolasi, Pengingat WhatsApp Saja

**Notifikasi Email Invoice**:
Email transaksional yang dikirim ke `Pelanggan.email` (bukan email akun Portal Pelanggan) untuk dua peristiwa: pengingat jatuh tempo tagihan dan konfirmasi pembayaran lunas, berisi link ke halaman invoice Portal Pelanggan tanpa lampiran PDF. Dikirim via SMTP Mailpit di lingkungan lokal; dilewati (skip + log) jika pelanggan tidak memiliki email.
_Technical Reference_: `App\Notifications\InvoiceReminderNotification`, `App\Notifications\InvoicePaymentConfirmedNotification`
_Avoid_: SMS Gateway (dihapus, tidak pernah diimplementasikan), Email ke Akun Portal Pelanggan


