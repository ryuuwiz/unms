# GOBILLING Domain Context

GOBILLING (formerly UNMS) ISP Network & Billing Management System staff application context and ubiquitous language.

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

**Administrasi**:
Kelompok navigasi untuk manajemen sistem, konfigurasi peran, dan kontrol hak akses pengguna.
_Avoid_: Administration, Settings, Pengaturan

**Pengguna**:
Entitas akun staff internal yang memiliki akses login dan hak akses ke sistem NMS.
_Avoid_: User, Staff Account, Member

**Peran**:
Kumpulan izin (permissions) yang diberikan kepada pengguna untuk membatasi akses fitur tertentu.
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

**Layanan Pelanggan**:
Entitas langganan aktif yang menghubungkan seorang pelanggan dengan paket layanan internet tertentu, router gateway, kredensial PPP, dan masa aktif.
_Avoid_: Subscription, Akun Internet, Koneksi

**PPP Username Credential**:
Identitas autentikasi PPPoE pelanggan di RouterOS dengan format `{No.Reg}_{NNNNN}` (contoh: `BF2308202601_00001`) — prefix adalah No.Reg pelanggan, suffix adalah counter 5 digit zero-padded unik per pelanggan (sequential: `max(counter) + 1`). Di-generate otomatis oleh sistem saat layanan dibuat; staff dapat override asal format dipatuhi. Disimpan di kolom `ppp_username` tabel `layanan_pelanggan`.
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
Dokumen tagihan pembayaran resmi atas layanan internet pelanggan dengan format penomoran `INV-YYYYMM-NNNNNN`.
_Avoid_: Tagihan Bebas, Kuitansi (sebelum dibayar), Bill

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
Catatan transaksi penerbitan tagihan digital (Xendit Hosted Invoice) ke payment gateway dengan identitas `external_id` unik untuk penjaminan idempotensi dan riwayat sesi pembayaran.
_Avoid_: Billing Gateway, Tagihan Xendit, Order ID Bebas

**Link Pembayaran Gateway**:
Tautan resmi sesi pembayaran terkelola Xendit (`xendit_invoice_url`) yang memuat pilihan metode bayar (VA, QRIS, e-wallet, dsb) secara langsung di halaman hosted Xendit.
_Avoid_: Custom Checkout URL, Link Bayar Bebas

**Log Webhook**:
Catatan audit trail penerimaan callback HTTP dari payment gateway Xendit untuk mencatat event id, payload mentah, status verifikasi token, dan proses eksekusi database.
_Avoid_: Callback History, Webhook Record

**Impersonasi**:
Aksi staf dengan peran `super_admin` untuk masuk sementara (*login as*) ke sesi pengguna staf lain atau akun portal pelanggan tanpa membutuhkan kata sandi untuk tujuan *troubleshooting*, audit hak akses, dan verifikasi tampilan portal secara *real-time*.
_Avoid_: Ghost Login, Bypass Auth, Switch User Bebas

**Tiket & Operasional**:
Kelompok navigasi untuk manajemen tiket layanan, penanganan aduan gangguan jaringan, dan pelacakan pekerjaan teknis lapangan.
_Avoid_: Support Desk, Helpdesk Umum, Tugas Lapangan

**Tiket**:
Entitas berkas kerja permohonan layanan atau penanganan masalah teknis (Pemasangan, Gangguan, Pencabutan, Pindah Alamat) dengan status siklus hidup dan penomoran otomatis terpusat.
_Avoid_: Issue, Aduan Bebas, Task, Case

**Nomor Tiket**:
Pengenal unik resmi untuk setiap tiket yang di-generate sistem secara terstandarisasi dengan format `TCK-YYYY-NNNNNN`.
_Avoid_: Ticket ID Bebas, No Aduan, Kode Masalah

**Histori Tiket**:
Catatan log kronologis *immutable* (hanya-baca) yang merekam setiap transisi status, pergantian PIC, dan catatan penanganan teknis.
_Avoid_: Riwayat Bebas, Log Tiket Manual, Catatan Lepas

**PIC (Person in Charge)**:
Staf pengguna internal (User) yang ditugaskan secara formal untuk bertanggung jawab menyelesaikan suatu tiket.
_Avoid_: Assignee, Petugas Lapangan Bebas, Pelaksana

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
_Avoid_: File Path Manual Bebas, Upload Lepas Tanpa Relasi

**Kartu Metrik (Stat Card)**:
Komponen visual modular (`<x-stat-card>`) untuk menampilkan ringkasan indikator performa utama (KPI) yang dilengkapi dengan tren perbandingan persentase periode, badge status, ikon bernuansa tematik, dan tautan navigasi kontekstual.
_Avoid_: Box Angka Bebas, Card Mentah, Stat Lepas

**Grafik Analitik (Chart Component)**:
Komponen visualisasi data interaktif berbasis ApexCharts yang terintegrasi dengan Alpine.js dan Livewire 4, mendukung tema dark-mode otomatis untuk menampilkan tren pendapatan 12-bulan, proporsi paket layanan, dan beban tiket operasional.
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


