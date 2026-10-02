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

**KTP Staf**:
Foto KTP milik Pengguna (staf internal), opsional, diunggah sendiri lewat Profil atau oleh admin saat onboarding. Diperlakukan setara KTP pelanggan: terenkripsi, hanya dilihat ber-watermark oleh pemiliknya dan admin, setiap akses tercatat. Pengguna tanpa KTP ditandai "KTP belum ada" di daftar Pengguna, tidak diblokir dari tugas apa pun.
_Avoid_: KTP Karyawan Publik, KTP Wajib untuk Jadi PIC

**Foto Profil**:
Foto diri Pengguna (staf internal), opsional, satu per Pengguna. Diunggah sendiri lewat Profil atau oleh admin atas nama Pengguna lain. Ditampilkan di mana pun Pengguna muncul sebagai orang: PIC dan Sales Penanggung Jawab di tiket, daftar Pengguna, dan menu akun. Pengguna tanpa foto ditampilkan dengan inisial nama, tidak diblokir dari tugas apa pun. Pelanggan tidak punya Foto Profil.
_Avoid_: Avatar Pelanggan, Foto Wajib untuk Jadi PIC

**Peran**:
Kumpulan izin (permissions) yang diberikan kepada pengguna untuk membatasi akses fitur tertentu.
_Technical Reference_: Spatie Permission (`spatie/laravel-permission`), Laravel Boost: `search-docs(packages=['spatie/laravel-permission'])`, Context7: `/spatie/laravel-permission`.
_Avoid_: Role, Group, Level

**Pelanggan**:
Entitas master data konsumen/klien ISP yang mencakup identitas kontak dan lokasi fisik pemasangan jaringan.
_Avoid_: Client, Customer Account, Member

**No. Registrasi**:
Pengenal unik untuk setiap pelanggan dengan format default `[Prefix][DDMMYYYY][Counter]` (contoh: `BF2309202601`, `ARS2309202601`), dibentuk dari Prefix Registrasi yang dipilih saat pendaftaran, dapat tetap di-custom penuh secara manual, dan dijamin unik di seluruh sistem.
_Avoid_: Customer ID, Nomor Pelanggan, No Langganan, CUST-XXXXXX, No Reg Duplikat

**Prefix Registrasi**:
Kode singkat (2-5 huruf kapital, contoh `BEST` untuk BESTFIBER, `WIFI` untuk WIFIGO, `MIIX` untuk MyArsyila) yang mewakili brand/unit bisnis dan menjadi awalan No. Registrasi pelanggan. Dikelola dinamis oleh staff lewat halaman Pengaturan Prefix Registrasi (tabel `pengaturan_prefix_registrasi`), dapat dinonaktifkan tanpa dihapus sehingga No. Registrasi lama yang sudah terbit tetap valid.
_Avoid_: Kode Cabang Hardcode, Prefix Bebas Tanpa Kelola

**Brand Pelanggan**:
Merek ISP yang dilihat seorang pelanggan: nama (dan logo) Prefix Registrasi yang cocok dengan huruf awal No. Registrasi-nya, **termasuk prefix yang sudah dinonaktifkan** (nonaktif hanya berarti tidak bisa dipilih untuk registrasi baru). No. Registrasi yang tidak cocok dengan prefix mana pun jatuh ke Nama Aplikasi (dan logo) Perusahaan. Satu-satunya brand yang tampil di setiap hal yang dilihat pelanggan (pesan WhatsApp, invoice PDF, deskripsi payment gateway) dan disebut di pesan staf tentang pelanggan itu. Brand hanya identitas merek (nama, nama pendek, logo, ikon aplikasi, warna utama); atribut yang belum diisi diturunkan dari brand itu sendiri (nama pendek dari nama, ikon dari logo, warna default aplikasi), tidak pernah dipinjam dari Perusahaan; identitas legal dan kontak tetap satu milik Perusahaan. Semua brand dikirim dari Koneksi Gateway WhatsApp yang sama.
_Avoid_: Brand Perusahaan untuk Pesan Pelanggan, Template WA per Brand, Nomor WA per Brand, Cabang

**Format Identitas Pelanggan**:
Format standar representasi identitas pelanggan untuk antarmuka staf backoffice dan selector sistem dengan susunan `[No. Reg]_[Nama Pelanggan]` (contoh: `WG2309202601_Budi Santoso`). Pada dropdown selector dilengkapi informasi sekunder di dalam kurung: `WG2309202601_Budi Santoso (0812xxxx • Cluster Melati)`.
_Avoid_: Nama Saja Tanpa No Reg, No Reg Tanpa Nama, Format Strip Tak Beraturan (Gunakan Format Baku `No. Reg_Nama`)

**Data Registrasi Billing**:
Entitas registrasi langganan billing (sebelumnya disebut Layanan Pelanggan, merujuk pada `docs/data_unms.md` bagian `# Layanan & Network -> ## Billing`) yang menghubungkan seorang pelanggan dengan paket layanan internet tertentu dan masa aktif. Dibuat murni komersial (paket, harga, alamat, tagihan pertama) berstatus `PROSES` — router gateway, alokasi IP Pool, dan kredensial PPP **belum diisi saat dibuat**, baru terisi lewat Aktivasi Pemasangan di dalam Ticket Pemasangan (lihat ADR terkait & `docs/plan/ticket-pemasangan-workflow.md`). Setiap Aktivasi divalidasi anti-duplikasi pada router & paket yang sama saat status masih aktif/proses/suspend, dengan tetap mendukung multi-site per pelanggan. Tidak memiliki halaman detail tersendiri — selalu ditampilkan di dalam halaman Detail Pelanggan (tab Subscriptions); dibuat lewat rute bertingkat `/layanan-pelanggan/create/{pelanggan}` yang mengunci pelanggan, bukan dipilih bebas.
_Avoid_: Subscription, Akun Internet, Koneksi, Layanan Saja, Router/PPP Terisi Saat Registrasi Dibuat

**Penghapusan Pelanggan**:
Menghapus seorang Pelanggan dari daftar (bisa dipulihkan dari tempat sampah), khusus `super_admin`, setelah mengetik `HAPUS` di modal peringatan. Ditolak selama Pelanggan masih punya Data Registrasi Billing yang belum `Berhenti` (termasuk yang masih `proses`) -- layanan harus diakhiri lebih dulu lewat Pencabutan atau dihapus satu per satu. Tidak butuh alasan; jejaknya cukup di audit trail.
_Avoid_: Hapus Kontak Pelanggan, Hapus Pelanggan Beserta Layanannya

**Penghapusan Data Registrasi Billing**:
Menghapus satu Data Registrasi Billing berstatus apa pun, khusus `super_admin`, setelah mengetik `HAPUS` di modal peringatan. PPP Secret-nya ikut dihapus dari router, IP Publik dan port ODP-nya dilepas. Invoice-nya tetap tercatat sebagai tunggakan seperti layanan Berhenti, tetapi pengingat WhatsApp dan link bayarnya dihentikan. Melunasi tunggakan itu tidak pernah menghidupkan layanan kembali. Jalan pintas di luar Pencabutan, bukan pengganti Pencabutan.
_Avoid_: Hapus Layanan sebagai Cara Berhenti Langganan

**PPP Username Credential**:
Identitas autentikasi PPPoE pelanggan di RouterOS dengan format `{No.Reg}_{NNNNN}` (contoh: `BF2308202601_84920`) — prefix adalah No.Reg pelanggan, suffix adalah 5-digit angka acak (*CSPRNG token* `10000`–`99999`) yang dijamin unik global di tabel `layanan_pelanggan`. Di-generate otomatis oleh sistem saat **Aktivasi Pemasangan** (bukan saat Data Registrasi Billing dibuat); staff dapat override lewat halaman Edit layanan asal format dipatuhi. Disimpan di kolom `ppp_username` tabel `layanan_pelanggan`.
_Avoid_: Username Bebas, PPP User Manual, Format Lama (`user_budi_01`), Digenerate Saat Registrasi Dibuat

**PPP Password Credential**:
Kredensial autentikasi PPPoE pelanggan yang di-generate sistem secara acak (8 karakter alfanumerik) pada langkah NOC (Aktivasi Pemasangan atau Proses NOC) bersamaan dengan PPP Username Credential, hanya bila masih kosong (tidak pernah menimpa yang sudah ada), atau saat di-reset lewat halaman Edit layanan. Satu-satunya pengecualian input manual: mode "Sudah Registrasi Mikrotik", di mana NOC mengetik password asli secret yang sudah ia buat sendiri di router (UNMS tidak memanggil RouterOS di mode ini, jadi password acak akan berbeda dari kenyataan). Tersembunyi secara default; diungkap lewat aksi *reveal* beraudit trail Spatie Activitylog (izin `layanan_pelanggan.lihat_ppp_password`, mengikuti pola Watermark Dokumen Identitas) oleh `super_admin` di mana saja, serta Teknisi dan NOC **hanya di dalam halaman tiket yang masih terbuka** dan yang boleh mereka akses (Teknisi: tiket yang PIC-nya dirinya). Tiket berstatus Selesai/Batal menyembunyikan aksi reveal.
_Avoid_: Password Manual Staf (kecuali mode Sudah Registrasi Mikrotik), Password Bebas, Plaintext Permanen di Halaman, Reveal Tanpa Audit, Password Digenerate Saat Registrasi Billing Dibuat

**Site ID**:
Pengenal unik titik instalasi layanan pelanggan berformat `[KodePrefix]APP[8 digit acak]` (contoh: `BFAPP48291037`; tanpa kode bila No. Registrasi tidak cocok dengan prefix mana pun, mis. `APP48291037`). Boleh diketahui pelanggan (nomor rujukan ke CS). Tidak berubah setelah terbit, termasuk bila No. Registrasi pelanggan kemudian berganti prefix. Site ID lama berformat `SITE-XXXXXXXX` sudah diganti sekali ke format ini (penggantiannya tercatat di riwayat layanan); comment PPP Secret di router dan deskripsi link bayar gateway yang sudah terbit masih memuat Site ID lama sampai diperbarui secara alami.
_Avoid_: Service ID, Lokasi ID, Site ID Mengikuti Perubahan Prefix

**Paket Layanan**:
Entitas katalog paket internet ISP yang menentukan profil bandwidth, tarif, dan masa aktif (hari/bulan). Tarif berlaku untuk **satu masa aktif penuh**, bukan per satuan — paket 3 Bulan seharga Rp 300.000 ditulis `Rp 300.000 / 3 Bulan`, bukan `/ Bulan`.
_Avoid_: Package, Product, Paket Data, Tarif per Satuan Masa Aktif

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
Blok alokasi alamat IP (Network, CIDR, Range IP) yang terikat pada Router untuk distribusi IP pelanggan PPPoE dinamis. Pool dipilih untuk sebuah paket di satu router lewat Router Paket; tidak ada staf yang memilih pool per pelanggan. Alamat `.1` (network + 1) selalu milik MikroTik dan tidak boleh berada di rentang. Nama pool unik per Router (bukan global), Range IP tidak boleh beririsan dengan pool lain pada Router yang sama, dan pool tidak dirangkai antar-pool. Pool yang dipakai Router Paket tidak bisa dihapus atau dipindah router.
_Avoid_: Subnet Bebas, DHCP Range, Dropdown Pilih IP Pool

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

**Pemantauan Sesi PPP**:
Pengamatan berkala sesi PPP aktif di setiap Router online untuk mendeteksi pelanggan yang connect, disconnect, atau reconnect (alamat sesi atau caller-id berubah), serta router yang berubah online/offline, lalu memberi tahu staf yang sedang membuka halaman pelanggan terkait secara langsung. Hanya mengamati, tidak pernah mengubah apa pun di router. Suspend/isolir dari UNMS ikut terlihat karena sesinya terputus. Uptime yang bertambah tidak dianggap perubahan.
_Avoid_: Polling Status, Rekonsiliasi Sesi, Sinkronisasi Status

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
Dokumen tagihan pembayaran resmi atas layanan internet pelanggan dengan nomor berformat `[KodePrefix]INV-[YYYYMMDD][7 digit acak]` (contoh: `BFINV-202609274839201`): kode Prefix Registrasi milik pelanggan (kosong bila No. Registrasi tidak cocok dengan prefix mana pun, mis. `INV-202609274839201`), tanggal terbit, dan 7 digit acak yang diacak ulang bila bentrok. Nomor yang sudah terbit tidak pernah diubah (juga bila No. Registrasi pelanggan kemudian berganti prefix), sehingga invoice lama tetap berformat `INV-[No.Reg]-[YYYYMM]-[Counter]` (lihat ADR-0062).
_Avoid_: Tagihan Bebas, Kuitansi (sebelum dibayar), Bill, Nomor Invoice Berurutan (mudah ditebak), Mengganti Nomor Invoice Lama

**Masa Aktif Hingga**:
Tanggal akhir masa aktif layanan yang dibeli sebuah invoice. Untuk invoice lunas, tanggal yang tercatat saat pelunasan memperpanjang masa aktif (tidak pernah dihitung ulang, karena masa aktif layanan sudah bergeser); untuk invoice yang masih terbuka, proyeksi bila dibayar sekarang. Invoice lunas dari sebelum pencatatan ini tidak punya Masa Aktif Hingga.
_Avoid_: Menghitung Hingga dari Expired Layanan Saat Ini untuk Invoice Lunas

**Invoice PDF**:
Dokumen cetak invoice untuk pelanggan, satu versi untuk staf dan pelanggan, berkop Brand Pelanggan. Rinciannya satu baris per komponen tagihan (paket periode itu, setiap add-on IP Publik Dedicated tanpa alamatnya, dan Tunggakan Akumulatif) sehingga subtotal selalu sama dengan yang ditagih. Metode bayar gateway ditulis sebagai channel yang benar-benar dipakai (mis. "Virtual Account BCA"); Sales / Teller adalah staf yang mencatat pembayaran, atau "Otomatis (Payment Gateway)".
_Avoid_: Username PPP/Router di Invoice, Qty = Jumlah Siklus Tunggakan, Invoice PDF Versi Staf

**Periode Tagihan**:
Identitas siklus bulan penagihan layanan (format `YYYY-MM`) yang memetakan kewajiban bayar langganan untuk satu siklus masa aktif dan menjamin batas 1 tagihan per layanan per siklus.
_Avoid_: Bulan Tagih Bebas, Periode Manual, Cycle ID

**Tenggat Pembayaran Invoice Pertama**:
Batas waktu H+1 (satu hari) dari tanggal mulai layanan untuk melunasi invoice pertama (ad-hoc, `periode_tagihan` NULL) yang terbit saat Data Registrasi Billing dibuat. Dicek setiap jam oleh command terpisah (`layanan:cek-tunggakan-pertama`, lihat ADR-0045) dari isolir bulanan biasa (`layanan:cek-isolir`, berbasis `tanggal_expired`) — hanya menyasar layanan yang belum pernah punya invoice periodik (masih di siklus pertama). Melewati tenggat ini men-suspend layanan lewat `UbahStatusLayananAction` yang sama seperti isolir tunggakan bulanan, sehingga PPP Secret ikut ter-disable realtime.
_Avoid_: Grace Period Bebas, Menyamakan dengan Hari Jatuh Tempo Siklus Tagihan, Isolir Manual Invoice Pertama

**Pembatalan Invoice**:
Tindakan perubahan status invoice menjadi `dibatalkan` oleh sistem atau staf berwenang (izin `invoice.batalkan`, default `admin` & `super_admin`) yang menggugurkan kewajiban bayar tanpa menghapus riwayat audit trail (misal akibat koreksi tagihan ganda atau perubahan paket). Berbeda dari Penghapusan Permanen Invoice, yang benar-benar menghancurkan baris datanya.
_Avoid_: Hapus Tagihan Manual, Void Bebas, Delete Invoice

**Pembatalan Invoice Lunas**:
Void invoice berstatus `lunas` (izin `invoice.void_lunas`, default hanya `super_admin`, alasan wajib) beserta rollback-nya: Pembayaran di-soft-delete sehingga keluar dari laporan, `tanggal_expired` layanan dikurangi sebanyak yang ditambahkan pelunasan itu (siklus digabung + bonus promo, disesuaikan ke Hari Jatuh Tempo), invoice yang tadinya digabung dilepas jadi `kadaluarsa`, dan layanan `aktif` yang masa aktifnya jadi lewat langsung di-suspend (PPP ikut disable). Invoice jadi `dibatalkan` + soft-delete; semuanya tercatat di audit trail. Hanya untuk pelunasan manual/transfer dan hanya pembayaran **terakhir** sebuah layanan (perpanjangan lebih baru bertumpuk di atasnya); pelunasan lewat payment gateway ditolak karena uangnya sudah diterima dan harus di-refund di gateway. Pemakaian promo tidak dikembalikan (sama seperti pembatalan invoice belum lunas).
_Avoid_: Hapus Invoice Lunas Tanpa Rollback, Membatalkan Pembayaran Lama di Tengah Riwayat, Void Pembayaran Gateway dari UI

**Penghapusan Permanen Invoice**:
Penghapusan sungguhan (`forceDelete`) sebuah invoice dari database, berbeda dari Pembatalan Invoice yang hanya mengubah status dan tetap menyimpan riwayat. Izin `invoice.hapus_permanen`, default hanya `super_admin`, alasan wajib dan dicatat ke audit trail sebelum baris dihapus (satu-satunya jejak yang tersisa setelahnya). Hanya berlaku untuk invoice yang sudah berstatus `dibatalkan` DAN tidak pernah punya riwayat Pembayaran (termasuk yang sudah soft-delete) atau Transaksi Payment Gateway sama sekali -- dijamin di level database lewat foreign key `restrictOnDelete` pada kedua tabel itu, sehingga invoice yang pernah dibayar atau pernah punya link pembayaran gateway tidak bisa dihapus permanen walau sudah dibatalkan.
_Avoid_: Menghapus Invoice yang Pernah Dibayar, Force Delete Tanpa Alasan, Mengubah Restrict Jadi Cascade di `pembayaran`/`transaksi_payment_gateway`

**Informasi Layanan di Tiket**:
Kartu di halaman detail tiket (semua Jenis Ticket yang punya layanan) berisi PPP Username, PPP Password (aturan reveal: lihat PPP Password Credential), Site ID, Router (nama + IP), Jenis Koneksi (PPPoE/IP Static) beserta IP Statis dan IP Publik Dedicated bila ada, Paket & Bandwidth, dan Status Layanan. Semua kecuali password terlihat oleh siapa pun yang boleh membuka tiket (Teknisi: PIC), pada status tiket apa pun; field yang belum diisi NOC tampil "Menunggu proses NOC". Alamat Sesi PPP dinamis tidak ditampilkan (dibaca live dari router).
_Avoid_: Router Ganda di Kartu Layanan dan Blok Infrastruktur, Menyembunyikan Info Non-Rahasia di Tiket Selesai

**Template Deskripsi Tagihan Gateway**:
Master data teks `description` yang dikirim ke payment gateway (Xendit) saat menerbitkan link bayar sebuah invoice periodik (`periode_tagihan` terisi) — teks yang tampil di bagian atas halaman checkout hosted dan dashboard gateway. CRUD beberapa template dengan tepat satu default (izin `payment_gateway.*`), placeholder divalidasi saat simpan (placeholder tak dikenal / konten kosong ditolak). Placeholder: `{brand}`, `{site_id}`, `{bulan}` (nama bulan + tahun dari `periode_tagihan`, mis. "Oktober 2026"), `{nama_paket_pelanggan}`, `{hingga}` (tanggal masa aktif layanan setelah invoice ini dibayar, `YYYY-MM-DD`, dihitung dengan rumus yang sama persis dengan `PerpanjangMasaAktifAction`), `{no_invoice}`, `{nama_pelanggan}`, `{no_reg}`, `{total_tagihan}`, `{jatuh_tempo}`, `{keterangan}`. `{brand}` adalah Brand Pelanggan (nama apa adanya sesuai yang diketik di Pengaturan, mis. "BESTFIBER"), sama dengan `{nama_brand}` WhatsApp. Invoice tanpa periode (tagihan pertama, manual) memakai teks tetap `{brand} - {keterangan} - Invoice {no_invoice}`; bila render kosong/gagal jatuh ke teks lama `Tagihan Internet UNMS Invoice {no_invoice}`; dipotong 255 karakter. Hanya berlaku untuk link bayar baru; link lama tidak berubah. `external_id` tidak diubah (dipakai pencocokan webhook). Nama bisnis di header checkout Xendit tidak bisa diatur lewat API (hanya dari profil bisnis akun Xendit).
_Avoid_: Mencampur dengan Template Pesan WhatsApp, Mengubah external_id demi Branding, Placeholder Tak Dikenal Lolos ke Halaman Checkout Pelanggan

**Tunggakan Akumulatif**:
Aturan penagihan di mana invoice siklus terbaru sebuah layanan memuat total bayar berupa tarif siklus itu ditambah seluruh invoice periodik sebelumnya yang belum dibayar pada layanan yang sama. Pelanggan hanya membayar invoice terbaru tersebut. Pembayaran memperpanjang masa aktif sebanyak siklus yang dicakup, dihitung dari tanggal expired lama (bukan tanggal bayar), sehingga bulan saat layanan diisolir tetap ditagih. Layanan berstatus `aktif` atau `suspend` terus menerima invoice tiap siklus sampai berstatus `berhenti`. Invoice ad-hoc (instalasi, denda) tidak digabung.
_Avoid_: Saldo Tunggakan, Piutang Bergulir

**Invoice Digabung**:
Status invoice periodik lama yang nominalnya sudah dipindahkan ke invoice siklus yang lebih baru (Tunggakan Akumulatif). Tidak lagi dihitung sebagai tagihan, tidak masuk Collection Rate, dan tidak dikirimi pengingat, namun tetap tersimpan sebagai riwayat. Berbeda dari Pembatalan Invoice yang menggugurkan kewajiban bayar.
_Avoid_: Dihapus, Void, Invoice Lunas Otomatis

**Pembayaran**:
Catatan transaksi penerimaan dana atas sebuah invoice yang memicu perpanjangan masa aktif layanan secara otomatis.
_Avoid_: Transaksi Kasar, Setoran

**Promo**:
Program diskon (nominal / persentase) atau bonus durasi yang dapat diaplikasikan pada penerbitan invoice.
_Avoid_: Voucher Bebas, Potongan Informal

**Portal Pelanggan**:
Antarmuka web mandiri untuk pelanggan internet ISP guna melihat informasi tagihan aktif, riwayat transaksi, profil langganan, dan melakukan pembayaran secara real-time. Tetap satu monolit dengan aplikasi staf (ADR-0007), tapi dapat diakses lewat domain khususnya sendiri (`app.portal_domain`, mis. `portal.gobilling.id`) sekaligus tetap hidup di path lama `/portal/*` pada domain staf (dipertahankan permanen untuk tautan tagihan bertanda tangan yang sudah terkirim) -- lihat ADR-0049. Tidak menampilkan PPP Username/Password, router, atau alamat IP. Tampil dengan identitas Brand Pelanggan (bukan Nama Aplikasi Perusahaan) dan dapat dipasang ke layar utama ponsel sebagai Aplikasi Pelanggan.
_Avoid_: Client Area Bebas, Customer App Terpisah, Halaman Member, Portal Sebagai Aplikasi Terpisah

**Aplikasi Pelanggan**:
Portal Pelanggan yang dipasang pelanggan ke layar utama ponselnya, dengan nama pendek dan ikon Brand Pelanggan miliknya. Bukan aplikasi app store dan bukan codebase terpisah: isi dan fiturnya persis Portal Pelanggan. Satu aplikasi per Brand Pelanggan, bukan per Perusahaan/tenant. Semua brand berbagi satu domain Portal, sehingga brand baru diketahui setelah login: pemasangan ditawarkan setelah login dan identitasnya mengikuti brand pelanggan yang login; satu ponsel memasang satu Aplikasi Pelanggan. Sebelum login tampil Petunjuk Brand atau, bila tidak ada, Nama Aplikasi Perusahaan. Tidak pernah menampilkan nama GOBILLING.
_Avoid_: Aplikasi Whitelabel Native, Aplikasi Play Store per Brand, Aplikasi Per Tenant, Domain per Brand, Powered by GOBILLING

**Petunjuk Brand**:
Brand Pelanggan terakhir yang login di sebuah perangkat, diingat perangkat itu agar halaman sebelum login (login, klaim akun, lupa kata sandi) tampil dengan brand yang sama dengan Aplikasi Pelanggan yang terpasang. Hanya menentukan tampilan, tidak pernah menentukan identitas atau hak akses; setelah login brand selalu diturunkan dari No. Registrasi.
_Avoid_: Brand dari Sesi Sebelumnya sebagai Identitas, Memilih Brand Manual di Halaman Login

**Kontak Dukungan**:
Tombol di Portal Pelanggan untuk menghubungi CS (WhatsApp dengan pesan terisi, telepon, email). Satu-satunya jalur dukungan dari Portal; Portal tidak membuat atau menampilkan tiket (ADR-0040). Kontaknya milik Perusahaan dan sama untuk semua Brand Pelanggan; pesan WhatsApp terisi menyebut Brand Pelanggan dan No. Registrasi.
_Avoid_: Tiket dari Portal, Live Chat, Layanan Dukungan Self-Service, Kontak CS per Brand

**Status Koneksi**:
Keadaan sambungan sebuah Layanan Pelanggan yang dilihat pelanggan di Portal: paket dan kecepatannya, status layanan (Aktif/Isolir/…), Online atau Offline, dan lama sesi saat ini, dibaca live dari router. Router yang tidak dapat dihubungi berarti "Status tidak tersedia", bukan Offline. Tidak memuat riwayat pemakaian (GB), router, alamat IP, maupun perangkat pelanggan.
_Avoid_: Penggunaan Bandwidth, Pemakaian Kuota, Grafik Trafik, Offline saat Router Tak Terjangkau

**Akun Pelanggan**:
Entitas kredensial autentikasi pengguna portal (guard `pelanggan`) yang terikat 1-to-1 dengan master data Pelanggan.
_Avoid_: User Pelanggan, Akun Web Bebas

**Transaksi Payment Gateway**:
Catatan transaksi penerbitan tagihan digital ke payment gateway (seperti Xendit, iPaymu) dengan identitas `external_id` unik untuk penjaminan idempotensi dan riwayat sesi pembayaran. Setiap transaksi terikat pada satu Koneksi Payment Gateway yang menerbitkannya; callback dan pengecekan status untuk transaksi itu selalu diverifikasi memakai kredensial koneksi tersebut, bukan koneksi default saat ini. Transaksi baru dianggap Kedaluwarsa bila gateway sendiri menyatakannya kedaluwarsa, bukan semata karena waktu lokal telah lewat. Transaksi yang diterbitkan Koneksi mode sandbox tidak pernah melunasi Invoice.
_Technical Reference_: Xendit PHP SDK (`xendit/xendit-php`), Context7: `/xendit/xendit-php`.
_Avoid_: Billing Gateway, Tagihan Xendit Saja, Order ID Bebas

**Link Pembayaran Gateway**:
Tautan resmi sesi pembayaran terkelola dari payment gateway aktif (`payment_gateway_url`) yang memuat pilihan metode bayar secara langsung di halaman hosted gateway tanpa form custom internal.
_Avoid_: Custom Checkout URL, Link Bayar Bebas, Xendit URL Saja

**Halaman Tagihan Mandiri**:
Halaman tunggal tanpa autentikasi/login yang dituju oleh tautan pada notifikasi WhatsApp pengingat tagihan, berisi rincian satu Invoice dan satu tombol yang mengarah ke Link Pembayaran Gateway. Tampil dengan Brand Pelanggan pemilik invoice (bukan Petunjuk Brand). Berbeda dari Portal Pelanggan (yang mencakup banyak halaman dan wajib login): halaman ini diakses via Tautan Tagihan yang mengikat ke satu Invoice spesifik dan tidak kedaluwarsa, tanpa form perbandingan biaya custom internal apa pun. Pelanggan yang sudah login ke Portal Pelanggan tetap dapat mencapai halaman yang sama via sesi login sebagai jalur kedua.
_Avoid_: Portal Pelanggan Saja, Halaman Bayar Terpisah, Custom Checkout Form, Kartu Estimasi Biaya Internal

**Tautan Tagihan**:
Alamat pendek dan tidak dapat ditebak yang membuka Halaman Tagihan Mandiri untuk satu Invoice tanpa login dan tanpa masa berlaku; siapa pun yang memegang tautan dapat melihat dan membayar invoice itu. Tautan bertanda tangan lama yang sudah terkirim tetap diterima meskipun tanggal kedaluwarsanya telah lewat.
_Avoid_: Link Bayar, Signed URL, Link Pembayaran Gateway

**Log Webhook**:
Catatan audit trail penerimaan callback HTTP dari payment gateway untuk mencatat event id, payload mentah, status verifikasi signature/token, dan proses eksekusi database.
_Avoid_: Callback History, Webhook Record, Xendit Webhook Saja

**Log Tugas Terjadwal**:
Catatan satu kali eksekusi tugas terjadwal sistem (perintah berkala seperti pembuatan invoice harian, cek isolir, rekonsiliasi pembayaran, ping router): nama perintah, waktu mulai dan selesai, durasi, status (Berhasil, Gagal, Dilewati), dan output error bila gagal. Tugas berfrekuensi tinggi (tiap menit atau lebih sering) hanya dicatat saat gagal. Berbeda dari job antrean Horizon, yang merupakan unit kerja asinkron hasil dispatch, bukan eksekusi jadwal.
_Avoid_: Cron Log, Scheduler History, Job Log

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
Entitas berkas kerja permohonan layanan atau penanganan masalah teknis (Pemasangan, Gangguan, Pencabutan, Pindah Alamat) dengan status siklus hidup dan penomoran otomatis terpusat. Dibuat oleh staf (sumber: `manual`/`sistem`). Tiket lama dengan sumber `portal` (dari fitur Portal Pelanggan yang telah dihapus, ADR-0040) tetap tersimpan sebagai riwayat.
_Avoid_: Issue, Aduan Bebas, Task, Case

**Alur Tiket ke Billing**:
Admin membuat Data Registrasi Billing (komersial, status `PROSES`, tagihan pertama langsung terbit) lebih dulu, baru kemudian membuat Ticket Pemasangan yang mengacu ke layanan itu. Ticket Pemasangan berjalan lewat sign-off 4 divisi (Teknisi, NOC, CS, Admin — lihat Status Per-Divisi Tiket); NOC men-Aktivasi Pemasangan di tengah alur itu (mengisi router/PPP, memicu layanan jadi `Aktif`). Status tiket keseluruhan otomatis `Selesai` begitu keempat divisi selesai, memicu Pelanggan jadi `PemasanganSelesai`. Batal → `BelumTerpasang`. Pencabutan Selesai → layanan terkait `Berhenti` (tagihan belum lunas tetap terbuka, hanya tagihan baru yang berhenti). Pindah Alamat Selesai → Admin diminta menerbitkan invoice manual biaya pindah. Pemasangan tidak mengubah Pelanggan yang sudah Aktif/Expired.
_Avoid_: Ticket Pemasangan Dibuat Sebelum Data Registrasi Billing, Aktivasi Otomatis Tanpa NOC, Invoice Otomatis Saat Tiket Selesai

**Status Pelanggan**:
Diturunkan dari seluruh Data Registrasi Billing pelanggan: `Aktif` jika ada layanan Aktif; `Expired` jika tidak ada yang Aktif tetapi ada yang Suspend; `Off` jika semua Berhenti. Selama belum ada layanan Aktif, tahap pemasangan (`BelumTerpasang`, `ReqPemasangan`, `PemasanganSelesai`) dikendalikan tiket Pemasangan. Pelanggan baru selalu `BelumTerpasang`; staf tidak memilih status saat pendaftaran.
_Avoid_: Status Manual per Layanan, Status Pelanggan Mengikuti Satu Layanan Saja

**Riwayat Tiket**:
Daftar semua Tiket milik satu Pelanggan, dilihat dari halaman detail Pelanggan, terbaru di atas. Hanya memuat tiket yang boleh dilihat user (teknisi: tiket yang ia pegang sebagai PIC; sales: tiket/pelanggan yang ia buat); tiket yang dihapus tidak ditampilkan. Berbeda dari Histori Tiket, yaitu jejak perubahan di dalam satu Tiket.
_Avoid_: Histori Ticket Pelanggan (rancu dengan histori di dalam satu tiket)

**Nomor Tiket**:
Pengenal unik resmi untuk setiap tiket yang di-generate sistem secara terstandarisasi dengan format `TCK-YYYY-NNNNNN`.
_Avoid_: Ticket ID Bebas, No Aduan, Kode Masalah

**Histori Tiket**:
Catatan log kronologis *immutable* (hanya-baca) yang merekam setiap transisi status, pergantian PIC, dan catatan penanganan teknis.
_Technical Reference_: Spatie Activitylog (`spatie/laravel-activitylog`), Laravel Boost: `search-docs(packages=['spatie/laravel-activitylog'])`, Context7: `/spatie/laravel-activitylog`.
_Avoid_: Riwayat Bebas, Log Tiket Manual, Catatan Lepas

**Foto Pengerjaan Lapangan**:
Foto bukti kerja Teknisi pada Ticket Pemasangan, dalam empat kategori: Speedtest dan Tanda Tangan MOU (wajib untuk Teknisi selesai), serta Bukti Pemasangan dan Foto Bersama Pelanggan & Teknisi (opsional).
_Avoid_: Foto Tahap 2, Lampiran Bebas

**Galeri Foto Tiket**:
Satu-satunya tempat melihat foto bukti sebuah tiket dalam ukuran besar, dikelompokkan per kategori: Foto Pengerjaan Lapangan, Foto Kendala, dan Foto Bukti Pengerjaan dari catatan Histori Tiket. Foto Profil staf bukan bagian galeri. Tempat lain di halaman tiket hanya mengunggah foto atau merujuk ke galeri, tidak menampilkan foto besar kedua kalinya.
_Avoid_: Thumbnail di Form Teknisi, Foto Tersebar per Kartu

**PIC (Person in Charge)**:
Staf pengguna internal (User) yang ditugaskan secara formal untuk bertanggung jawab menyelesaikan suatu tiket.
_Avoid_: Assignee, Petugas Lapangan Bebas, Pelaksana

**Sales Penanggung Jawab**:
Pengguna ber-peran Sales yang mendaftarkan pelanggan, ditampilkan (foto + nama) di tiket Pemasangan dan Pindah Alamat saja — tiket Gangguan dan Pencabutan hanya menampilkan PIC. Bila pendaftar bukan Sales, tiket menampilkan "Tanpa Sales"; Sales yang sudah nonaktif tetap ditampilkan dengan penanda Nonaktif (data historis). Bukan pembuat tiket.
_Avoid_: Sales yang Menangani, Sales Pembuat Tiket, Sales Ditugaskan per Tiket

**Divisi Tiket**:
Satu atau lebih divisi internal (Admin, Customer Service, Sales, NOC, Teknisi) yang bertanggung jawab menangani sebuah tiket, disimpan dalam relasi many-to-many via pivot table `ticket_divisi`. Tiket lama bersumber portal untuk Gangguan otomatis ditugaskan ke [NOC, Teknisi] untuk mendukung koordinasi penjadwalan; Pencabutan dan Pindah Alamat ke [Teknisi]. Sejak alur Ticket Pemasangan (lihat Status Per-Divisi Tiket), pivot ini juga menyimpan status sign-off per divisi, tidak lagi sekadar penandaan keterlibatan.
_Avoid_: Divisi Tunggal per Tiket, Single Enum Divisi

**Status Per-Divisi Tiket**:
Kolom `status` (`belum` / `progress` / `selesai`) pada pivot `ticket_divisi`, satu per baris divisi — dipakai penuh hanya oleh Ticket Pemasangan. Teknisi memakai ketiga nilai (progress = sudah pilih ODP+port & upload ≥1 foto pemasangan); NOC, Customer Service, dan Admin praktiknya cuma lompat `belum`→`selesai`. Tidak ada rantai urutan wajib antar-divisi selain dua gate eksplisit: Aktivasi Pemasangan butuh Teknisi minimal `progress`, dan Admin `selesai` butuh invoice pertama layanan sudah Lunas. Begitu keempat divisi `selesai`, status tiket keseluruhan (`StatusTicket`) otomatis berpindah ke `Selesai` lewat `UbahStatusTicketAction` yang sudah ada — staf tidak lagi menyelesaikan tiket Pemasangan secara manual terpisah.
_Avoid_: Status Tiket Tunggal untuk Pemasangan, Urutan NOC→CS→Admin Dipaksa Tanpa Alasan, Admin Selesai Sebelum Pelanggan Bayar

**Aktivasi Pemasangan**:
Aksi NOC di dalam Ticket Pemasangan, khusus pengisian **pertama kali** (`layanan_pelanggan_id` masih tanpa router), yang mengisi router gateway pada Data Registrasi Billing yang masih `PROSES`, lalu memicu provisioning PPP Secret ke MikroTik dan mengubah layanan jadi `Aktif`. Hanya Router yang **dipilih manual oleh NOC** (topologi jaringan/lokasi customer tidak bisa diturunkan dari paket; satu paket ritel dijual lintas-router). IP Pool **tidak dipilih NOC** — layanan memakai pool Router Paket paketnya di router terpilih; hanya router yang terdaftar sebagai Router Paket untuk paket itu yang bisa dipilih. PPP Username Credential tetap auto-generate. Berbeda dari tombol "Provisi" di daftar layanan (`Index.php::provisionLayanan()`) yang cuma retry provisioning untuk layanan yang router/PPP-nya sudah terisi. Profil Bandwidth ditampilkan read-only **hanya di modal ini** (mengikuti paket sejak pendaftaran, tidak bisa diganti saat pengisian pertama kali) — aturan read-only ini TIDAK berlaku lagi begitu layanan sudah pernah diaktivasi: perubahan router/paket berikutnya (misalnya Gangguan yang butuh pindah router) lewat modal Proses NOC, lihat "Proses Divisi (NOC/Admin/Customer Service)".
_Avoid_: Router Diturunkan Otomatis dari Paket, NOC Memilih IP Pool, Mengganti Profil Bandwidth Saat Aktivasi, Memakai Modal Aktivasi Pemasangan untuk Perubahan Router/Paket Setelah Layanan Aktif

**Proses Divisi (NOC/Admin/Customer Service)**:
Modal tunggal "Proses {Divisi}" yang menggantikan pola lama "Tandai Divisi Selesai" untuk divisi NOC, Admin, dan Customer Service (Teknisi tetap memakai tombol "Tandai Selesai" + form Progress Lapangan/Foto Tahap 2 karena digate bukti foto, bukan catatan bebas). Hanya tersedia di Ticket Pemasangan yang mengacu ke layanan; Gangguan, Pencabutan, dan Pindah Alamat diselesaikan lewat perubahan status tiket keseluruhan (Ubah Status), bukan sign-off per divisi. Field "Status Ticket" di dalamnya sebenarnya adalah Status Per-Divisi Tiket milik divisi itu sendiri (On Progress/Selesai), ditambah opsi terpisah "Cancel" yang membatalkan tiket keseluruhan (`StatusTicket::Batal` lewat `UbahStatusTicketAction`), bukan status per-divisi. Field teknis (Mode Registrasi Mikrotik, Router, Paket — IP Pool tidak dipilih, lihat "Router Paket") hanya tampil kalau tiket sudah punya `layanan_pelanggan_id` dan aksinya relevan (mis. Gangguan yang perlu re-provision router pada layanan yang sudah `Aktif` — inilah beda utamanya dengan Aktivasi Pemasangan yang khusus pengisian pertama kali). Field "Catatan Proses" wajib diisi, menyatu dalam satu baris Histori Tiket yang sama dengan perubahan status divisi (bukan modal "Tambah Catatan" terpisah, yang tetap ada untuk update informal).
_Avoid_: Memisah Modal per Aksi Teknis, Catatan Proses sebagai Modal Terpisah dari Perubahan Status Divisi, Opsi Cancel Mengubah Status Per-Divisi

**Mode Registrasi Mikrotik**:
Pilihan di modal Proses NOC yang menentukan apakah sistem perlu memanggil RouterOS API. "Proses Registrasi Mikrotik" memanggil `MikrotikService::createOrUpdatePppoeSecret()` seperti Aktivasi Pemasangan (gagal → toast peringatan + `MikrotikJobLog` berstatus Gagal, field router/paket/PPP tetap tersimpan di database). "Sudah Registrasi Mikrotik" hanya menyimpan field ke database + mencatat Histori Tiket, tanpa panggilan RouterOS API sama sekali — dipakai saat NOC sudah menyiapkan PPP secara manual di router sebelum memproses tiket.
_Avoid_: Selalu Memanggil RouterOS Tanpa Opsi Manual, Menyembunyikan Kegagalan Mikrotik Tanpa MikrotikJobLog

**Panduan Alur Tiket**:
Bacaan langkah kerja per divisi untuk satu Jenis Ticket, dibuka dari halaman detail tiket agar staf baru memahami alur (Pemasangan, Gangguan, Pencabutan, Pindah Alamat). Hanya memuat divisi yang ditugaskan ke tiket itu, diawali langkah yang terjadi sebelum tiket dibuat bila ada (mis. Data Registrasi Billing untuk Pemasangan). Tiap divisi menampilkan Status Per-Divisi-nya yang sudah ada sebagai penanda; panduan sendiri tidak menyimpan status atau centang apa pun. Isinya mengikuti aturan sistem (gate, syarat selesai), jadi ikut berubah bila aturan itu berubah. Terlihat oleh semua yang boleh membuka tiket.
_Avoid_: SOP, Tutorial, Checklist Tiket (menyiratkan ada status tersimpan), Panduan Khusus Staf Baru

**Catatan Internal Tiket**:
Entri Histori Tiket yang ditandai `is_internal = true` — hanya terlihat oleh staf. Sejak Portal Pelanggan tidak lagi menampilkan tiket (ADR-0040), semua catatan praktis bersifat internal; flag dipertahankan tanpa perubahan skema.
_Avoid_: Menyembunyikan Semua Histori dari Pelanggan, Menampilkan Catatan Teknis Mentah Tanpa Filter

**Target SLA**:
Batas waktu tenggat penyelesaian tiket yang dihitung otomatis berdasarkan skala prioritas saat tiket pertama kali dibuat.
_Avoid_: Deadline Bebas, Target Waktu, Estimasi Jam

**Profil Perusahaan**:
Entitas identitas legal, Nama Aplikasi, alamat operasional, kontak bantuan, dan catatan resmi yang dikonfigurasi untuk kop tagihan (invoice header), kuitansi digital, dan antarmuka portal pelanggan. Tidak menyimpan NPWP maupun rekening bank, dan invoice tidak mencantumkan rekening. Transfer bank manual diurus Customer Service di luar sistem; staf hanya mencatat pembayaran sebagai Manual (Admin/Kasir) atau lewat payment gateway. Metode "Transfer Bank" hanya tersisa pada pembayaran lama.
_Avoid_: Company Setting Bebas, Header Manual, Info PT Lepas, Rekening Transfer Manual di Invoice

**Nama Aplikasi**:
Nama tampilan milik Perusahaan untuk aplikasi staf (sidebar, halaman login, judul tab). Bersama logo Perusahaan menjadi identitas cadangan bagi pelanggan yang No. Registrasi-nya tidak cocok dengan Prefix Registrasi mana pun, dan bagi Portal sebelum login tanpa Petunjuk Brand. Bukan brand: kata "brand" selalu berarti Brand Pelanggan.
_Avoid_: Brand Perusahaan, Nama Brand

**Kesiapan Multi-Tenant (Multi-Tenant Readiness)**:
Arsitektur pemisahan entitas data organisasi/perusahaan yang dirancang dengan skema relasional `perusahaan_id` (record default `is_default=true` untuk mode internal) sehingga dapat dinaikkan menjadi platform SaaS multi-penyewa di masa depan tanpa mengubah model domain inti.
_Avoid_: Hardcoded Single Company, Multi Database Terpisah Tanpa Pola

**Berkas Media (Media Library)**:
Pengelolaan berkas digital (logo instansi, foto identitas/KTP, foto dokumentasi teknis tiket, dan bukti transfer pembayaran) yang terpusat melalui relasi polimorfik Spatie MediaLibrary dengan penanganan otomatis konversi gambar, mime checking, dan siklus hidup berkas.
_Technical Reference_: Spatie MediaLibrary (`spatie/laravel-medialibrary`), Laravel Boost: `search-docs(packages=['spatie/laravel-medialibrary'])`, Context7: `/spatie/laravel-medialibrary`.
_Avoid_: File Path Manual Bebas, Upload Lepas Tanpa Relasi

**Media Publik**:
Berkas Media yang memang ditujukan untuk dilihat siapa saja tanpa login: logo Perusahaan, logo Prefix Registrasi (Brand Pelanggan), dan ikon aplikasi. URL-nya permanen. Gambar dari Media Library yang dipilih menjadi logo disalin menjadi Media Publik.
_Avoid_: Bucket Publik untuk Semua Media

**Media Privat**:
Semua Berkas Media selain Media Publik (foto tiket, foto profil, berkas umum). Hanya ditampilkan lewat URL bertanda tangan yang kedaluwarsa dalam 60 menit, dibuat untuk staf yang boleh membuka halamannya; URL yang bocor mati sendiri. KTP dan dokumen Pelanggan punya aturan sendiri (dienkripsi, lihat Media Library (Admin)).
_Avoid_: URL Permanen untuk Foto Tiket, Path Berurutan yang Bisa Ditebak

**Failover Penyimpanan**:
Upload Berkas Media yang tetap berhasil ketika penyimpanan S3 tidak terjangkau: berkas disimpan sementara di penyimpanan server sendiri (Media Privat tetap privat) lalu dipindah otomatis ke S3 begitu S3 pulih. Hanya untuk upload baru; berkas yang sudah di S3 tidak tampil selama S3 mati.
_Avoid_: Upload Gagal karena S3 Mati, Media Privat Mendarat di Folder Publik

**Media Library (Admin)**:
Halaman admin tunggal (menu Administrasi, permission `media_library.lihat`/`.hapus`/`.unggah`, khusus `super_admin`) yang menggabungkan tiga hal: (1) browse & hapus manual **semua** berkas Berkas Media lintas model, **kecuali** collection `ktp` dan `dokumen` milik Pelanggan (lihat ADR-0024 dan ADR-0046) — keduanya cuma muncul sebagai angka statistik, tidak bisa dilihat/diunduh dari sini; (2) kesehatan koneksi disk S3/RustFS aktif dan statistik pemakaian per collection (dulu halaman terpisah "Storage & S3 Monitoring", digabung — lihat ADR-0047); (3) unggah bebas berkas gambar/dokumen (jpg/png/webp/pdf/xlsx/xls/csv/docx, maks 20MB) yang tidak terkait record bisnis manapun, dianchor ke model kosong `BerkasUmum` (lihat ADR-0047). Pengecekan kesehatan storage bukan cuma tanya SDK (bucket exists) — juga benar-benar fetch satu URL publik lewat HTTP client, satu-satunya cara mendeteksi kebijakan public-read bucket yang salah/dicabut (lihat ADR-0038, gejalanya invisible dari sisi Laravel, baru ketahuan 403 saat browser fetch).
_Technical Reference_: `App\Services\Storage\S3HealthCheckService`, `App\Models\BerkasUmum`.
_Avoid_: Menampilkan KTP/Dokumen di Browser Generik, Menghapus Media Privat Tanpa Audit Trail, Cek SDK Saja Tanpa Fetch URL Publik Nyata, Sembunyikan Menu Total Saat Disk Bukan S3 (tampilkan status "tidak berlaku" saja), Halaman Storage & S3 Monitoring Terpisah

**Media Library Picker**:
Pola UI (modal grid gambar) untuk memilih satu berkas gambar yang sudah ada di Media Library sebagai nilai suatu field, tanpa mengunggah berkas baru — dipakai pertama kali untuk field Logo Perusahaan. Memilih selalu men-**salin** (`Media::copy()`) berkas ke collection tujuan, tidak pernah memindahkan/reassign media asli — supaya berkas yang sudah dipakai di tempat lain (foto tiket, foto profil user) tidak diam-diam berubah makna atau hilang dari pemiliknya semula (lihat ADR-0048). Aturan visibilitas (gambar apa saja yang boleh dipilih, mengecualikan dokumen pribadi Pelanggan) satu sumber lewat `App\Support\MediaLibraryVisibility`, sama dengan yang dipakai halaman Media Library sendiri.
_Technical Reference_: `App\Support\MediaLibraryVisibility`.
_Avoid_: Reassign Media Asli ke Owner Baru, Duplikasi Aturan Pengecualian KTP/Dokumen di Setiap Picker

**Kartu Metrik (Stat Card)**:
Komponen visual modular (`<x-stat-card>`) untuk menampilkan ringkasan indikator performa utama (KPI) yang dilengkapi dengan tren perbandingan persentase periode, badge status, ikon bernuansa tematik, dan tautan navigasi kontekstual.
_Avoid_: Box Angka Bebas, Card Mentah, Stat Lepas

**Grafik Analitik (Chart Component)**:
Komponen visualisasi data interaktif berbasis ApexCharts yang terintegrasi dengan Alpine.js dan Livewire 4, mendukung tema dark-mode otomatis untuk menampilkan tren pendapatan 12-bulan, proporsi paket layanan, dan beban tiket operasional.
_Technical Reference_: Livewire 4 (`livewire/livewire`) & Flux UI (`livewire/flux`), Laravel Boost: `search-docs(packages=['livewire/livewire', 'livewire/flux'])`, Context7: `/websites/livewire_laravel_4_x`.
_Avoid_: Gambar Grafik Statis, Chart Canvas Tanpa Reaktivitas

**Collection Rate**:
Rasio efektivitas penagihan dalam persentase pada satu Periode Tagihan: nominal lunas dibanding total nominal yang ditagihkan, dihitung dari tarif siklus itu saja (tanpa tunggakan yang ikut terbawa), tidak termasuk invoice `dibatalkan` namun tetap memasukkan invoice `digabung`. Ditampilkan di Dashboard sebagai bilah progres "tertagih".
_Avoid_: Persen Bayar Bebas, Efektivitas Kas, CR

**Belum Dibayar**:
Total nominal seluruh invoice berstatus `menunggu_pembayaran` atau `kadaluarsa` pada semua periode, termasuk yang belum jatuh tempo (uang yang sudah ditagihkan namun belum diterima). Tidak dibatasi Periode Tagihan agar tidak terpengaruh penggabungan tunggakan ke invoice siklus berikutnya. Di Dashboard tampil sebagai baris kecil "total belum dibayar, semua periode" pada Ringkasan Tagihan Periode.
_Avoid_: Piutang Bebas, Tagihan Nunggak

**Pendapatan Diterima**:
Total nominal Pembayaran yang dicatat (`dibayar_pada`) dalam satu rentang tanggal atas dasar kas (cash basis), terlepas dari Periode Tagihan invoice yang dibayar. Di Dashboard ada dua ukuran: Hari Ini (dengan pembanding nominal "Kemarin", tanpa persentase karena hari ini belum penuh) dan Bulan Ini (dibandingkan dengan rentang hari yang sama pada bulan sebelumnya, 1 s/d tanggal hari ini, bukan total bulan sebelumnya penuh).
_Avoid_: Omzet, Pendapatan Tertagih, Revenue

**Layanan Expired**:
Layanan pelanggan berstatus `aktif` atau `suspend` yang tanggal expired-nya sudah lewat paling lama 30 hari ke belakang. Semua layanan jatuh tempo pada tanggal 10 setiap bulan, dan layanan yang belum dibayar diisolir (suspend PPP) otomatis oleh sistem sesudahnya. Layanan yang sudah lewat lebih dari 30 hari dianggap urusan pembersihan (ditutup sebagai `berhenti`), bukan tindak lanjut harian.
_Avoid_: Member Hangus, Langganan Mati, Akun Basi

**Ringkasan Tagihan Periode**:
Kartu Dashboard untuk Periode Tagihan bulan berjalan (siklus yang jatuh tempo pada Hari Jatuh Tempo bulan itu): Ditagih, Lunas, Belum Lunas (Ditagih dikurangi Lunas, dalam tarif siklus tanpa tunggakan), Collection Rate, dan baris kecil Belum Dibayar semua periode. Invoice siklus bulan depan baru masuk ringkasan pada bulan itu.
_Avoid_: Tagihan Bulan Ini, Rekap Invoice

**Tren Pendapatan Harian**:
Grafik sumbu-ganda (*dual-axis*) di Dashboard untuk 30 hari terakhir sampai hari ini (rentang bergulir, bukan bulan berjalan): kurva nominal Pendapatan Diterima harian (sumbu primer) dan jumlah transaksi pembayaran harian (sumbu sekunder). Hari mendatang tidak digambar.
_Avoid_: Grafik Omzet Harian Lepas, Chart Transaksi Terpisah

**Total Pelanggan**:
Jumlah Pelanggan berstatus `aktif`, `off`, atau `expired` di Dashboard, dipecah menjadi Aktif dan Tidak Aktif (`off` + `expired`). Pelanggan calon (belum terpasang, req. pemasangan, pemasangan selesai) tidak dihitung. Mengikuti status Pelanggan, bukan status Layanan: status Pelanggan hanya diubah manual Aktif/Off, sehingga pelanggan yang seluruh layanannya suspend tetap terhitung Aktif sampai diubah.
_Avoid_: Jumlah Member, Pelanggan Terkoneksi

**Pelanggan Expired & Jatuh Tempo**:
Daftar tindak lanjut harian di Dashboard yang berisi Layanan Expired ditambah layanan yang akan jatuh tempo dalam jendela Lead Time Penerbitan Invoice. Setiap baris adalah satu layanan (pelanggan dengan dua layanan muncul dua kali). Diurutkan: tanggal expired terlama, lalu nominal invoice terbuka terbesar, lalu nama pelanggan. Terlihat oleh semua staf yang berhak melihat Layanan Pelanggan.
_Avoid_: Perlu Perhatian, Daftar Tunggakan, Antrean Isolir

**Tagihan Terbuka per Pelanggan**:
Daftar di Dashboard berisi 20 Pelanggan dengan total invoice terbuka (menunggu pembayaran atau kadaluarsa) terbesar, dijumlahkan lintas semua layanan dan periode milik pelanggan itu. Invoice yang sudah digabung ke invoice berikutnya tidak dihitung dua kali. Berbeda dari Pelanggan Expired & Jatuh Tempo: sudut pandangnya per pelanggan dan nominal, bukan per layanan dan tanggal.
_Avoid_: Ringkasan Tagihan Pelanggan, Daftar Penunggak, Ringkasan Tagihan Periode

**Antrian Tiket Saya**:
Tiket terbuka (bukan Selesai atau Batal) yang menunggu tindakan Pengguna yang sedang login. Untuk Teknisi: tiket yang PIC-nya dirinya. Untuk peran lain: tiket yang ditugaskan ke divisinya dan Status Per-Divisi divisinya belum selesai.
_Avoid_: Tiket Assigned, Tiket Saya (PIC) untuk non-Teknisi

**Area Dashboard**:
Kelompok widget Dashboard per bidang kerja: Admin (pelanggan dan keuangan), NOC & Infrastruktur, serta Ticketing & Support. Sebuah area tampil bila Pengguna berhak melihat minimal satu widget di dalamnya; area tidak terikat ke peran tertentu.
_Avoid_: Dashboard per Role, Halaman Divisi

**Siklus Tagihan**:
Pengaturan bulanan yang dapat diubah admin berisi dua hari-dalam-bulan: Hari Jatuh Tempo (contoh: 10) dan Hari Terbit Invoice (contoh: 24). Invoice tiap layanan terbit pada Hari Terbit terakhir sebelum jatuh tempo (jatuh tempo tanggal 10 berarti terbit tanggal 24 bulan sebelumnya) dan jatuh tempo pada Hari Jatuh Tempo; tanggal expired layanan mengikuti Hari Jatuh Tempo saat pembayaran memperpanjang masa aktif. Perubahan pengaturan hanya berlaku pada invoice yang terbit sesudahnya dan pada perpanjangan berikutnya. Seluruh paket berdurasi tepat 1 bulan.
_Avoid_: Jadwal Tagihan Bebas, Tanggal Cetak

**Lead Time Penerbitan Invoice**:
Selisih hari antara Hari Terbit Invoice dan Hari Jatuh Tempo pada Siklus Tagihan (dengan nilai bawaan 24 dan 10 sekitar 16 hari, H-16). Nilainya diturunkan dari Siklus Tagihan, bukan diinput terpisah. Jendela "akan jatuh tempo" pada Perlu Perhatian mengikuti nilai ini.
_Avoid_: Masa Tenggang, Grace Period

**Provisi Router Otomatis (Automatic Router Provisioning)**:
Pipeline orkestrasi berurutan yang menyelaraskan seluruh data konfigurasi master UNMS ke perangkat MikroTik RouterOS melalui RouterOS API (pemeriksaan koneksi & resource $\rightarrow$ IP Pool & Simple Queue $\rightarrow$ Profil Bandwidth biner $\rightarrow$ PPP Secret Layanan Pelanggan $\rightarrow$ sinkronisasi status isolir dan pemutusan sesi aktif).
_Avoid_: Sync Parsial Tanpa Urutan, Push Manual Bebas, Provisi Terfragmentasi

**Rekonsiliasi Router (Router Reconciliation)**:
Proses komparasi periodik terjadwal dan auto-recovery antara basis data UNMS dengan konfigurasi aktual di RouterOS untuk mendeteksi *configuration drift*, memulihkan PPP secret/profil yang hilang atau terhapus di router, dan menyelaraskan status disabled.
_Avoid_: Cek Status Lepas, Sync Buta, Ping Tanpa Rekonsiliasi

**Provisi Cadangan**:
Jaring pengaman terjadwal (tiap menit) untuk layanan yang sudah punya router dan username PPP tetapi belum berhasil diprovisi, misalnya karena job antrean hilang, gagal, atau tidak pernah dijalankan. Layanan diambil alih hanya bila tidak ada percobaan provisi dalam 5 menit terakhir dan routernya tidak tercatat offline. Galat yang sama dengan percobaan sebelumnya tidak diberitahukan ulang ke NOC (tetap tercatat di Job Log). Berbeda dari Rekonsiliasi Router yang membandingkan isi router dengan database untuk layanan yang sudah terprovisi.
_Avoid_: Provisi Ulang Berebut dengan Job Antrean, Notifikasi Berulang untuk Galat yang Sama

**Gate Proaktif Router Offline**:
Pemeriksaan `Router.status_koneksi` (hasil `mikrotik:ping` tiap 10 detik) di awal `EnablePppoeAccountJob`/`DisablePppoeAccountJob` sebelum mencoba konek ke RouterOS. Jika diketahui `offline`, job dihentikan dengan `MikrotikJobLog` berstatus `Dilewati` (bukan `Gagal`) tanpa notifikasi kegagalan, dan penyelarasan sebenarnya diserahkan ke siklus Rekonsiliasi Router 15-menit berikutnya. Kredensial yang salah (bukan router mati) tetap tertangkap jalur reaktif `getClient()` seperti biasa — gate ini murni mengurangi percobaan & notifikasi sia-sia untuk kasus yang sudah diketahui, bukan pengganti validasi koneksi.
_Avoid_: Menganggap Dilewati sebagai Gagal, Notifikasi Kegagalan untuk Router yang Memang Mati, Skip Permanen Tanpa Percobaan Ulang

**Orphaned Secret (PPP Secret Tak Terkelola)**:
Akun PPP Secret di RouterOS yang namanya tidak cocok dengan username PPP layanan mana pun di router itu. Tanpa penanda di router, orphan tidak bisa dibedakan dari secret manual NOC, jadi sistem tidak pernah menghapusnya dan tidak menyediakan pembersihan orphan; ia hanya bisa dilaporkan.
_Avoid_: Hapus Buta Akun Router, Pembersihan Orphan, Comment sebagai Penanda Kepemilikan

**Secret Milik Billing / Secret Manual NOC**:
Kepemilikan objek di router ditentukan namanya, bukan komentar: secret milik billing bila namanya sama dengan username PPP sebuah layanan di router itu; profile dan pool milik billing bila namanya sama dengan data billing. Selain itu (secret/profile manual NOC, `default`) tidak pernah disentuh sistem. Konfigurasi yang dibuat billing di router tidak diberi komentar; komentar `UNMS:` lama dikosongkan saat rekonsiliasi.
_Avoid_: Comment `UNMS:` sebagai Penanda Kepemilikan, Menyentuh Objek Router yang Namanya Tidak Ada di Billing

**Penghapusan PPP Secret**:
Satu-satunya jalur yang boleh menghapus secret di router. Wajib membawa jejak input (siapa dan alasan), menolak username kosong, dicatat di Job Log beserta snapshot secret tanpa password. Pemicu yang sah: **Proses NOC pada tiket Pencabutan** (sebelum layanan Berhenti), pindah router, ganti username, atau layanan dihapus. **Isolir tidak pernah menghapus**, dan data pelanggan serta layanan di billing tidak pernah ikut dihapus.
_Avoid_: Hapus Otomatis Tanpa Input, Penghapusan Tanpa Alasan atau Actor, Isolir Berujung Hapus, Berhenti Tanpa Tiket Pencabutan

**IP Pool & Router Gateway Layanan**:
Layanan PPPoE hanya terikat ke satu Router (dipilih NOC dari Router Paket paketnya); IP-nya dialokasikan RouterOS dari pool Router Paket itu lewat profile, tidak pernah oleh billing. Billing tidak menampilkan local-address; IP pelanggan dibaca dari sesi aktif. Pindah router atau paket berlaku saat sesi reconnect. IP Statis dan IP Publik Dedicated memakai alamat literal di PPP Secret yang menimpa alamat profile.
_Avoid_: Billing Mengalokasikan IP, Memilih IP Pool per Layanan, Menampilkan Local Address, Nama Pool sebagai Remote Address di PPP Secret (RouterOS menolaknya; nama pool hanya valid di Profile PPP)

**Isolir**:
Layanan yang ditangguhkan (status `Suspend`: masa aktif habis, invoice pertama belum dibayar, atau isolir manual admin) dipindahkan PPP Secret-nya ke satu profile `EXPIRED` di router lalu sesinya diputus agar pelanggan tersambung ulang ke profile itu. Secret tidak pernah di-*disable*. Buka isolir mengembalikan secret ke profile paketnya dan memutus sesi lagi. Profile `EXPIRED` (kecepatan kecil, alamat dari IP Pool Isolir) dibuat billing di setiap router tanpa input admin. Secret yang masih ter-*disable* (cara isolir lama) diaktifkan kembali oleh rekonsiliasi.
_Avoid_: Disable Secret untuk Isolir, Profile Isolir Berbeda per Alasan Suspend, Menghapus Secret saat Isolir

**Pencabutan**:
Satu-satunya jalan layanan menjadi `Berhenti`: tiket Pencabutan yang hanya dibuat Admin (dan super_admin), ditangani NOC (menghapus PPP Secret di router) dan Teknisi (mencabut perangkat dan melepas port ODP). Tiket tidak bisa Selesai sebelum penghapusan secret berhasil; saat Selesai layanan otomatis Berhenti. Data pelanggan dan layanan tetap tersimpan untuk jejak pelanggan bermasalah, dan layanan Berhenti tidak lagi ditagih: invoice yang masih terbuka tetap tercatat sebagai tunggakan, tetapi pengingat WhatsApp dan link bayarnya dihentikan. Melunasi tunggakan tidak memperpanjang masa aktif maupun mengaktifkan layanan kembali.
_Avoid_: Berhenti dari Form Edit Layanan, Menghapus Data Pelanggan saat Pencabutan

**Router Paket**:
Pasangan Paket Layanan dengan Router yang boleh menjualnya, beserta satu IP Pool milik router itu dan deskripsi opsional, dikelola di Detail Paket oleh pemegang izin ubah paket dan NOC. Setiap Router Paket membuat satu PPP Profile bernama sama dengan paket di router itu: `rate-limit` dari Profil Bandwidth paket, `local-address` = `.1` pool terpilih (alamat MikroTik), `remote-address` = pool terpilih (MikroTik yang membagikan IP pelanggan). NOC hanya bisa memilih router yang sudah menjadi Router Paket untuk paket layanan itu. Router Paket tidak bisa dihapus selama masih ada layanan (selain Berhenti) memakai paket itu di router itu. Tidak ada rantai antar-pool; pool penuh ditangani dengan mengganti pool Router Paket.
_Avoid_: Rantai IP Pool (next-pool), Profile per Profil Bandwidth, NOC Memilih Pool per Pelanggan, Router Bebas untuk Paket Apa Saja

**IP Pool Isolir**:
Satu IP Pool per router yang dibuat billing dari subnet isolir yang sama untuk semua router, dipakai profile `EXPIRED` (local-address `.1` pool itu, remote-address pool itu). Admin tidak memilihnya. Bila subnet itu sudah dipakai objek lain di router, billing tidak menimpanya: router itu tidak bisa mengisolir dan NOC diberi tahu. Pool ini tidak boleh dipakai Router Paket dan tidak bisa dihapus selama menjadi IP Pool Isolir. Firewall redirect pelanggan isolir ke halaman bayar dikonfigurasi manual oleh NOC, bukan billing.
_Avoid_: Billing Membuat Firewall Redirect, Pool Isolir Dipakai untuk Paket

**Alamat Sesi PPP**:
IP yang benar-benar dipegang pelanggan dinamis saat ini. Dialokasikan oleh RouterOS (bukan UNMS) dan dibaca live dari `/ppp/active` (per pelanggan) serta `/ip/pool/used` (pemakaian pool), dengan cache singkat. UNMS tidak menyimpan atau menetapkan alamat ini; kolom `ip_dynamic` tidak lagi ditulis.
_Avoid_: Alokasi IP Dinamis oleh UNMS, Snapshot IP Sesi di Database sebagai Sumber Kebenaran

**Alokasi IP Statis Layanan**:
Pengalokasian alamat IPv4 statis dedicated (kolom `ip_static` di `layanan_pelanggan`) untuk pelanggan dengan jenis koneksi IP Static yang langsung diset sebagai `remote-address` pada konfigurasi PPP Secret MikroTik.
_Avoid_: IP Statis Tanpa Validasi IPv4, Alokasi Statis Tak Tercatat di Database

**IP Publik Dedicated**:
Add-on berbayar berupa satu alamat IPv4 publik dari inventaris milik sebuah Router, ditetapkan ke satu Layanan Pelanggan PPPoE dan ditagih bulanan bersama tagihan paket. Alamatnya menjadi `remote-address` literal pada PPP Secret (bersama gateway inventaris sebagai `local-address`) yang menimpa alamat profile paket. Berstatus `tersedia` atau `terpakai`, dipegang layanan selama `Suspend`, dan dilepas otomatis saat layanan `Berhenti`/dihapus. Alamatnya tidak boleh berada di dalam rentang IP Pool mana pun pada router yang sama. Penetapan atau pelepasan memutus sesi aktif agar berlaku segera. Harga bulanan disalin (snapshot) ke baris inventaris saat penetapan.
_Avoid_: Memakai `ip_static` untuk IP Publik, Paket yang Sudah Termasuk IP Publik, Jenis Koneksi Baru untuk IP Publik, Prorata Otomatis Add-on

**Cleanup PPP Secret Lama (Router Migration Cleanup)**:
Proses otomatis yang dipicu oleh Observer saat `router_id` sebuah Layanan Pelanggan berubah (migrasi router). Sistem menghapus PPP Secret dari router lama via job antrian (`CleanupPppSecretOnOldRouterJob`) agar tidak terjadi duplikat secret lintas router. Jika router lama offline, kegagalan dicatat di `MikrotikJobLog` sebagai Failed untuk tindak lanjut manual.
_Avoid_: Biarkan Secret Lama, Hapus Manual, Duplikat Dibiarkan Sampai Rekonsiliasi

**Multi-Layanan Pelanggan (Granular Billing & Isolation)**:
Dukungan di mana satu pelanggan (entitas `Pelanggan`) dapat memiliki banyak layanan internet aktif (entitas `LayananPelanggan` 1:N). Setiap layanan memiliki `site_id` dan `ppp_username` berurutan unik (`{no_reg}_00001`, `{no_reg}_00002`), paket layanan independen, serta siklus invoice tersendiri (`Invoice` 1:1 per periode per layanan). Jika salah satu layanan menunggak (Suspend), isolir dilakukan secara parsial hanya pada PPP Secret layanan yang bersangkutan tanpa mengganggu layanan lain milik pelanggan yang sama.
_Avoid_: Akumulasi Invoice Tanpa Rincian Site, Isolir Global Seluruh Layanan Pelanggan, Duplikasi Pelanggan untuk Multi-Lokasi

**Manajemen Local & Remote Address PPP Secret**:
Untuk PPPoE dinamis, `local-address` dan `remote-address` pada PPP Secret sengaja dikosongkan; keduanya dibawa profile paket (Router Paket). Hanya IP Statis dan IP Publik Dedicated yang mengisi `remote-address` literal (dan `local-address` gateway) di secret. Tetap ada *auto-ensure* IP Pool sebelum provisi, validasi relasi router ketat (*scoped validation*), dan rekonsiliasi periodik dengan *auto-healing* yang juga mengosongkan nilai literal lama pada secret dinamis.
_Avoid_: Local/Remote Address Literal di Secret PPPoE Dinamis, Provisi PPPoE Tanpa IP Pool

**ODP (Optical Distribution Point)**:
Titik terminasi fisik kabel distribusi serat optik luar ruang tempat tersambungnya kabel drop instalasi pelanggan, memiliki kapasitas port terukur (4, 8, 16, 24, 32), deskripsi/PON, dan koordinat geografis presisi untuk perhitungan jalur pemasangan jaringan.
_Avoid_: Box ODP Bebas, Kotak Fiber Lepas, ODP Tanpa Koordinat

**ODP Terdekat**:
Saran ODP untuk pelanggan baru/diubah berdasarkan koordinat: maksimal 3 ODP dengan jarak lurus paling dekat, hanya yang berada dalam toleransi **150 meter** dari lokasi pelanggan. ODP di luar toleransi tidak disarankan.
_Avoid_: Radius 300 Meter, Menyarankan ODP di Luar Toleransi

**Penghapusan ODP**:
Penghapusan (permanen, satuan maupun massal lewat pilihan baris / "pilih semua hasil pencarian") hanya untuk ODP yang tidak punya port Terpakai oleh layanan; ODP yang portnya masih dipakai dilewati dan dilaporkan, karena menghapusnya diam-diam melepas ikatan port dari layanan pelanggan. Hapus massal >10 ODP wajib konfirmasi ketik `HAPUS`; setiap penghapusan tercatat di audit trail (nama & ID ODP).
_Avoid_: Tombol Kosongkan Seluruh Tabel ODP, Melepas Port Layanan Diam-diam

**Port ODP**:
Slot fisik terminasi pada perangkat ODP yang melacak status pemakaian (*kosong, terpakai, rusak*) dan terikat 1:1 dengan satu entitas Layanan Pelanggan (Data Registrasi Billing). Port Kosong yang sudah dipilih Teknisi pada Ticket Pemasangan lain yang masih terbuka dianggap **Dipesan**: tidak bisa dipilih tiket lain dan ditampilkan "Dipesan oleh {nomor tiket}" di pilihan port Teknisi. Dipesan bukan status tersimpan; ia hilang sendiri saat tiket Batal, dan menjadi Terpakai saat Aktivasi Pemasangan.
_Avoid_: Colokan Kabel, Slot ODP Lepas, Status Port Dipesan Tersimpan

**Peta Port ODP**:
Tampilan seluruh Port ODP pada satu ODP sebagai kotak bernomor yang menandai status masing-masing (Kosong, Terpakai, Rusak, Dipesan, dipilih tiket ini) beserta ringkasan pemakaiannya. Port Terpakai ditandai dengan Site ID layanan pemakainya (bukan No. Registrasi, yang bisa berganti prefix dan bisa sama untuk beberapa layanan), dengan nama pelanggan dan No. Registrasi sebagai keterangan saat dipilih. Menjadi cara Teknisi memilih port di Ticket Pemasangan (hanya port Kosong atau port milik tiket itu sendiri yang bisa dipilih), dan tampil baca-saja dengan port layanan disorot di tiket lain yang layanannya sudah punya port.
_Avoid_: Dropdown Port Kosong Saja, Menyembunyikan Port Terpakai

**Label Port**:
Stiker thermal 50×30 mm untuk satu Port ODP yang dipakai sebuah layanan, berisi nama ODP dan nomor port, nama pelanggan, No. Registrasi, dan Site ID sebagai pengenal yang tidak berubah. Dicetak dari tiket yang sudah punya port (Dipesan maupun Terpakai, kecuali Pencabutan) atau secara massal dari halaman ODP untuk port Terpakai; pencetakan tidak tercatat di Histori Tiket. Satu label per port, bukan daftar per ODP.
_Avoid_: Label ODP Berisi Daftar Port, Label Tanpa Site ID, Barcode di Label Port

**Usulan ODP**:
ODP yang dipilih pembuat Ticket Pemasangan (siapa pun perannya) saat membuat tiket yang merujuk layanan, hanya dari ODP Terdekat terhadap koordinat layanan itu yang masih punya port kosong. Wajib diisi bila ada kandidat; bila tidak ada ODP berport kosong dalam jangkauan, tiket tetap dibuat dengan tanda "Tanpa ODP dalam jangkauan" dan Teknisi memilih ODP sendiri. Layanan tanpa koordinat harus dilengkapi koordinatnya dulu. Berstatus `Menunggu Validasi Teknisi` sampai Teknisi menanganinya: `Disetujui` (Teknisi memakai ODP itu) atau `Diganti Teknisi` (Teknisi menolak dengan alasan tercatat di Histori Tiket dan memilih ODP lain sendiri, tanpa kembali ke pembuat tiket). Teknisi baru boleh memilih Port ODP setelah usulan berstatus `Disetujui` atau `Diganti Teknisi`. Hanya menentukan ODP, bukan port. Validasi memakai izin yang sama dengan progress lapangan Teknisi. Tiket Pemasangan tanpa layanan dan tiket lama tanpa Usulan ODP tetap memakai alur lama (Teknisi memilih ODP bebas).
_Avoid_: Sales Memilih Port, Penolakan Dikembalikan ke Sales, Usulan ODP di Luar Toleransi ODP Terdekat

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

**Koneksi Gateway WhatsApp (Sysblas)**:
Entitas konfigurasi satu akun/nomor pengirim WhatsApp (WAHA atau GOWA) yang menyimpan kredensial, provider, dan parameter throughput-nya sendiri, memungkinkan beberapa nomor WhatsApp berjalan independen dalam satu instalasi (misal nomor billing terpisah dari nomor pengaduan tiket).
_Technical Reference_: Model `App\Models\Sysblas`, tabel `sysblas` (nama tabel warisan dari provider WABLAS lama; provider aktual kini WAHA/GOWA). Menu: SysBlast > Koneksi API.
_Avoid_: Akun WhatsApp, Gateway WA Tunggal, WhatsApp Gateway (istilah lama yang salah kaprah dipakai untuk Template Pesan WhatsApp)

**Template Pesan WhatsApp**:
Master data isi teks pesan notifikasi otomatis (tagihan, konfirmasi pembayaran, tiket) berformat placeholder dinamis (`{nama_pelanggan}`, `{no_reg}`, dst.), independen dari Koneksi Gateway WhatsApp mana pun yang sedang dipakai untuk mengirimnya — mengelola *isi pesan*, bukan kredensial/koneksi. Satu template dipakai semua brand; `{nama_brand}` berisi Brand Pelanggan penerima (bukan Nama Aplikasi Perusahaan). Pesan ke pelanggan tidak pernah memuat PPP Username/Password, router, atau alamat IP.
_Technical Reference_: Model `App\Models\WaTemplate`, `App\Livewire\Settings\WhatsappSettings`, menu SysBlast > Template Pesan.
_Avoid_: WhatsApp Gateway, Pengaturan WhatsApp Gateway

**Batas Laju Pengiriman (Send Rate Limit)**:
Plafon jumlah percobaan pengiriman per Koneksi Gateway WhatsApp dalam jendela 60 detik — dihitung dari setiap percobaan kirim, bukan hanya yang sukses; percobaan yang berujung gagal permanen tetap mengonsumsi plafon ini.
_Technical Reference_: Kolom `limit_per_menit` pada `Sysblas`, `RateLimiter::hit()`/`tooManyAttempts()` di `KirimWaBlastJob`.
_Avoid_: Batas Pesan, Quota Blast

**Jeda Antar-Pesan (Inter-Message Cooldown)**:
Jarak waktu minimum wajib (dengan variasi acak/jitter) antara dua pengiriman berurutan pada Koneksi Gateway WhatsApp yang sama, ditegakkan lebih dulu daripada Batas Laju Pengiriman, sebagai mekanisme anti-ban utama agar pola pengiriman tidak terdeteksi sebagai bot. Kedua mekanisme wajib dikonfigurasi konsisten terhadap satu target throughput yang sama — jeda yang lebih longgar dari `60 / limit_per_menit` detik membuat Batas Laju Pengiriman tidak pernah tercapai dan menjadi tidak berefek.
_Technical Reference_: Kolom `delay_detik` & `jitter_detik` pada `Sysblas`, Cache slot `sysblas-next-send-slot-*` di `KirimWaBlastJob`.
_Avoid_: Delay, Sleep Pengiriman

**Penyapu Antrean Macet (Stalled Queue Sweeper)**:
Command terjadwal yang hanya men-dispatch ulang baris antrian blast berstatus `Menunggu` yang tertinggal kembali ke job pengiriman ber-rate-limit, tanpa pernah mengirim pesan secara langsung — menjamin satu-satunya jalur pengiriman nyata tetap menghormati Batas Laju Pengiriman dan Jeda Antar-Pesan.
_Technical Reference_: `wa:proses-antrian` (`App\Console\Commands\ProsesAntrianWaCommand`), men-dispatch `KirimWaBlastJob`.
_Avoid_: Batch Sender Kedua, Jalur Kirim Paralel

**Pengingat Tagihan Otomatis**:
Sistem pengingat tagihan terjadwal (`invoice:kirim-pengingat`) yang berjalan setiap jam untuk mengevaluasi aturan pengingat aktif dan mengirimkan notifikasi berformat template dinamis dengan link pembayaran lewat WhatsApp (antrean `wa-blast` Horizon dengan perlindungan pembatasan laju). Sebelumnya juga lewat email; dihapus karena WhatsApp sudah mencakup peristiwa yang sama dan menjaga satu kanal lebih mudah dipantau risiko volumenya.
_Avoid_: Pengiriman Manual Satu Per Satu, Blast Tanpa Antrean Terisolasi, Mengirim Ulang Lewat Email

**Notifikasi NOC**:
Pemberitahuan ke user berperan NOC dan super admin tentang hasil integrasi MikroTik. Lonceng di aplikasi menampilkan hasil aksi per pelanggan (provisi, un-isolir, ganti profil berhasil/gagal) serta perubahan status router online/offline. WhatsApp hanya untuk kejadian genting: aksi per pelanggan yang gagal permanen. Router online/offline sengaja TIDAK lewat WhatsApp (hanya lonceng) — tiap flip status membuat log baru yang tidak ter-dedup per hari, sehingga router yang flapping bisa memicu banyak pesan WA berturut-turut dan berisiko nomor gateway WA kena banned. Isolir massal yang berhasil tidak diberitahukan; tidak ada notifikasi per sesi PPPoE pelanggan naik/turun.
_Avoid_: Notifikasi Tersimpan Tanpa Tampilan, WhatsApp untuk Setiap Keberhasilan, Notifikasi Sesi PPPoE, WhatsApp untuk Router Online/Offline

**Notifikasi Invoice Terbit**:
Pesan tagihan baru (rincian + tautan pembayaran bertanda tangan) yang dikirim ke pelanggan begitu sebuah Invoice dibuat — tagihan pertama saat Data Registrasi Billing dibuat, tagihan periodik oleh `invoice:generate`, maupun invoice manual (biaya instalasi, denda) — lewat WhatsApp (template `invoice_terbit`, antrean `wa-blast`). Dipicu event `InvoiceTerbitEvent` dari `BillingService` setelah transaksi commit, bukan observer model, sehingga seeder/factory tidak mengirim pesan. Hanya batch `invoice:generate` (invoice tanpa pembuat) yang dibuat di luar jendela siang (07:00–20:00 WIB) ditunda ke 08:30 berikutnya; invoice buatan admin (tagihan pertama, manual) selalu langsung dikirim karena pelanggan biasanya sedang dilayani. Pesan yang menunggu giliran Jeda Antar-Pesan tidak dianggap gagal; galat gateway sementara (timeout, 5xx) dicoba ulang hingga 2 jam, lalu dianggap gagal dan tercatat di log WhatsApp. Tanpa opt-out per invoice; pelanggan tanpa no. HP valid dilewati + log; kegagalan kirim tidak pernah menggagalkan pembuatan invoice. Terpisah dari Pengingat Tagihan Otomatis (aturan H-3/H-1/H0/tunggakan tetap apa adanya dan bisa tumpang-tindih dengan pesan ini; admin boleh menonaktifkan aturan H-3).
_Avoid_: Observer Invoice::created (memicu pesan dari seeder/factory), Mengandalkan Aturan H-3 sebagai Notifikasi Terbit (tagihan pertama jatuh tempo H+1 sehingga tidak pernah cocok), Pesan Malam Hari Tanpa Penundaan, Email Invoice Terbit (dihapus)

**Gateway WA Bermasalah**:
Keadaan ketika gateway WhatsApp (WAHA/GOWA) menolak autentikasi (401/403) atau tidak terjangkau (timeout, 5xx), sehingga semua pesan ke pelanggan gagal. Admin dan super admin diberi tahu lewat lonceng maksimal sekali per jam per gateway. Kegagalan per nomor (nomor tidak valid/tidak terdaftar) bukan Gateway WA Bermasalah; cukup terlihat di halaman Antrian WA dan log WhatsApp.
_Avoid_: Lonceng untuk Setiap Pesan Gagal


**Ekspor Data Layanan**:
Ekspor Excel satu baris per Data Registrasi Billing (layanan), bukan per pelanggan: pelanggan, Site ID, paket, status layanan, `tanggal_expired`. Difilter per paket dan status layanan; "Client Expired" berarti layanan yang `tanggal_expired`-nya sudah lewat (definisi yang sama dengan filter "expired" di daftar layanan), apa pun status layanannya — bukan status Pelanggan `Expired` yang diturunkan.
_Avoid_: Ekspor per Pelanggan dengan Paket Digabung, Menyamakan Client Expired dengan Status Pelanggan Expired

**NIK di Laporan Keuangan**:
Kolom NIK pada ekspor Laporan Billing hanya terisi bila pengunduh memegang izin `pelanggan.lihat_ktp` (selain itu "-"); setiap ekspor berisi NIK tercatat di audit trail, dan tombol ekspor laporan mensyaratkan izin `laporan.ekspor`.
_Avoid_: NIK untuk Semua Pemegang laporan.lihat, Ekspor NIK Tanpa Jejak Audit

**Riwayat Tiket**:
Halaman lintas-tiket yang menampilkan entri Histori Tiket (perubahan status, siapa, kapan, catatan) dari semua tiket, dapat difilter per status dan rentang tanggal. Berbeda dari daftar tiket (status tiket saat ini) dan dari Histori Tiket di halaman detail satu tiket.
_Avoid_: Menyamakan Riwayat Tiket dengan Filter Status Daftar Tiket

**Jenis Barang**:
Item inventaris gudang yang stoknya dihitung dalam jumlah (kabel, konektor, modem), dikelompokkan dalam Kategori Barang (mis. `MDM` = Modem). Jenis barang yang dilacak per unit (modem/ONT) juga memiliki Unit Barang.
_Avoid_: Barang Tanpa Kategori, Stok Diketik Manual

**Unit Barang**:
Satu fisik barang yang dilacak (mis. satu modem) dengan Kode Barang dan barcode sendiri, sehingga dapat ditelusuri ke tiket/pelanggan tujuannya. Barang habis pakai (kabel, konektor) tidak memiliki unit.
_Avoid_: Unit untuk Barang Habis Pakai

**Kode Barang**:
Kode unik per Unit Barang yang di-generate saat Barang Masuk dan tidak pernah dipakai ulang, berformat `[KATEGORI]-[KONDISI]-[BRAND?]-[NOMOR]` (BRAND = kode Prefix Registrasi, mis. `BF`) (contoh `MDM-NEW-BF-240` modem baru, `MDM-PGT-240` modem pergantian). Kategori dan Kondisi (`NEW` baru, `PGT` pergantian) dikelola di pengaturan dan hanya bisa dihapus bila tidak dipakai (penghitung kodenya tidak ikut dihapus, jadi kode yang dibuat ulang melanjutkan nomor); nomor increment per prefix lengkap (`MDM-NEW-BF` dan `MDM-PGT` punya urutan sendiri). Kode `PGT` hanya di-generate untuk barang bekas yang masuk tanpa kode (modem lama dari pelanggan pra-sistem, barang bekas); unit yang sudah berkode dan dikembalikan tetap memakai kodenya, kondisinya tercatat di status/riwayat unit.
_Avoid_: Nomor Increment Global, Kode Diketik Manual, Memakai Ulang Kode Unit yang Keluar

**Status Unit Barang**:
Siklus hidup Unit Barang: `di_gudang` → `terpasang` (Barang Keluar ke teknisi/tiket) → `dikembalikan` (masuk lagi sebagai kondisi `PGT`, kode unit tetap sama) atau `rusak` (dihapusbukukan). Barang Keluar untuk jenis barang yang dilacak per unit wajib memilih/memindai unit spesifik, bukan mengetik jumlah.
_Avoid_: Mengganti Kode Unit Saat Dikembalikan, Barang Keluar Modem Tanpa Unit

**Saldo Awal Barang**:
Barang Masuk khusus untuk stok yang sudah ada sebelum sistem dipakai, diinput sekali per jenis barang lewat isian Stok Awal di Data Barang (bukan di form Barang Masuk), dibedakan dari pembelian nyata di laporan. Hanya bisa diisi selama jenis barang belum punya mutasi; untuk barang yang dilacak per unit, stok awal membuat unit berkode baru.
_Avoid_: Saldo Awal sebagai Pembelian Biasa, Saldo Awal dari Form Barang Masuk

**Barang Masuk / Barang Keluar**:
Mutasi stok yang menjadi satu-satunya sumber angka stok. Barang Masuk tidak dipilih tipenya oleh staf: unit Terpasang yang dipindai kembali tercatat sebagai Pengembalian, selain itu Pembelian. Barang Keluar mencatat tanggal, jumlah, Keperluan, keterangan, satu atau lebih Teknisi penerima (User peran teknisi, setara tanpa penerima utama), dan opsional tiket tujuan; stok tidak boleh negatif. Barang Keluar yang ditandai rusak (dihapusbukukan) tidak wajib punya teknisi. Dicatat oleh Admin/NOC; Teknisi hanya melihat. Label barcode Code128 dicetak sebagai PDF.
_Avoid_: Mengubah Angka Stok Langsung, Barang Keluar Tanpa Teknisi (kecuali rusak), Teknisi Penerima Utama

**Keperluan Barang Keluar**:
Teks bebas yang menjelaskan untuk apa barang keluar (mis. "Pemasangan", "Maintenance POP", "Dipinjam kantor"), diisi staf dengan saran dari nilai yang pernah dipakai. Berbeda dari Tipe Mutasi (Pemakaian / Rusak) yang ditentukan sistem dari tanda rusak dan mengatur status unit.
_Avoid_: Tipe (untuk isian bebas), Keperluan Mengatur Status Unit

**Penghapusan Mutasi**:
Barang Masuk atau Barang Keluar yang salah catat boleh dihapus (bukan diedit), dan efeknya dibalik: unit yang dibuat oleh Barang Masuk ikut terhapus, sedangkan status dan kondisi unit lain dipulihkan ke keadaan sebelum mutasi itu. Ditolak bila mutasi bukan yang terakhir untuk salah satu unitnya, atau bila stok barang jadi negatif di tanggal mana pun sesudahnya. Nomor kode unit yang terhapus tidak dipakai ulang.
_Avoid_: Mengedit Mutasi, Menghapus Mutasi di Tengah Riwayat Unit, Menghapus Data Barang yang Punya Mutasi

**Stok Periode**:
Rekap stok per bulan per Jenis Barang: Stok Awal (= Stok Akhir bulan sebelumnya), Masuk, Keluar, Stok Akhir (= Awal + Masuk − Keluar), selalu dihitung dari mutasi, tidak disimpan sebagai angka yang bisa diedit.
Data Barang dapat difilter per nama/kategori dan per jumlah stok (mis. Stok Akhir ≤ N, stok habis) serta diurutkan per jumlah stok.
_Avoid_: Stok Awal Diinput Tiap Bulan, Stok Tersimpan Terpisah dari Mutasi

**Impor Inventaris**:
Migrasi satu kali spreadsheet gudang lama saat sistem mulai dipakai: satu berkas Excel berisi 3 sheet (Data Barang, Barang Masuk, Barang Keluar) yang di-parse ke tabel pratinjau lalu disimpan sekaligus (semua-atau-tidak, satu transaksi) hanya bila tanpa galat. Sheet Data Barang membuat/memperbarui Jenis Barang; STOK AWAL-nya menjadi mutasi Saldo Awal, sedangkan MASUK/KELUAR/STOK AKHIR hanya dipakai sebagai pemeriksaan silang (selisih = peringatan). Kategori/satuan/mode lacak dari kolom opsional; tanpa kolom KATEGORI, kategori diambil dari prefix kode bila dikenal, selain itu masuk kategori `UMUM`. Berkas gudang asli diterima apa adanya: nama sheet tanpa beda huruf besar/kecil, baris judul di atas header, rumus dibaca nilai hasil hitungnya, kode barang boleh berisi spasi dan `: / .`. Baris barang dilacak di sheet Masuk/Keluar memakai kode unit asli (mis. `MDM-NEW-BF-240`) yang dipertahankan, dan penghitung kode per prefix dimajukan agar kode baru tidak bentrok. Kolom TEKNIS/TIM TEKNIS: setiap nama anggota tim menjadi teknisi penerima (dicocokkan ke user teknisi, atau dipetakan manual di pratinjau); KETERANGAN berisi EROR/CACAT/RUSAK dicatat sebagai barang Rusak (boleh tanpa teknisi, mis. "All"). Barang Masuk tanpa tanggal jatuh di awal periode berkas (judul "PERIODE …" atau bulan paling awal). Sheet Masuk punya kolom opsional TIPE (`SALDO AWAL`/`PEMBELIAN`/`PENGEMBALIAN`); stok awal barang dilacak wajib lewat baris Saldo Awal berkode unit, bukan angka STOK AWAL. Kondisi & brand unit diurai dari segmen kodenya. Tanggal berbentuk bulan saja (`09/2026`) jatuh pada hari terakhir bulan itu. Validasi memutar ulang mutasi per barang secara kronologis (stok tidak boleh negatif). Ditolak bila sudah ada mutasi apa pun; pengulangan hanya lewat perintah reset inventaris yang disengaja.
_Avoid_: Impor Berulang yang Menggandakan Stok, Menyimpan Baris Valid Saja, Mengganti Kode Unit Lama Saat Impor

**RAB Kantor**:
Rancangan Anggaran Belanja internal kantor per bulan: daftar rencana pengeluaran yang dicatat staf dan dilihat per bulan. Hanya rencana, tanpa persetujuan dan tanpa pencatatan realisasi. Bisa disalin dari bulan sebelumnya ke bulan yang masih kosong.
_Avoid_: Anggaran, Budget, RAB Proyek

**Item RAB**:
Satu baris rencana dalam RAB Kantor: Uraian, Qty, Harga satuan, dan Divisi RAB. Jumlah = Qty × Harga; total bulan = jumlah semua Item RAB bulan itu.
_Avoid_: Baris Anggaran, Pos Belanja

**Divisi RAB**:
Penanggung jawab sebuah Item RAB: salah satu divisi dari Divisi Tiket **atau** teks bebas (nama orang atau beberapa orang). Hanya label, tidak menugaskan atau berhubungan dengan tiket.
_Avoid_: Divisi Tiket (untuk konteks RAB)

**Bulan Terkunci**:
Bulan RAB Kantor sebelum bulan berjalan. Item RAB di bulan itu tidak bisa ditambah, diubah, dihapus, atau diisi lewat salin, kecuali oleh staf yang diberi izin buka kunci. Tetap bisa dilihat dan diekspor.
_Avoid_: Tutup Buku, Arsip
