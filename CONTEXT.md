# UNMS Domain Context

Ubiquiti & ISP Network Management System (UNMS) staff application context and ubiquitous language.

## Language

**Menu Utama**:
Kelompok navigasi utama untuk fitur operasional harian yang dapat diakses oleh staff sesuai hak aksesnya.
_Avoid_: Platform, Main Navigation, Navigasi

**Keuangan & Billing**:
Kelompok navigasi untuk manajemen tagihan invoice, penerimaan pembayaran, promo, dan laporan keuangan.
_Avoid_: Finance, Kasir, Akuntansi

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
Pengenal unik terstandarisasi sistem untuk setiap pelanggan (format `REG-YYYY-NNNNNN`).
_Avoid_: Customer ID, Nomor Pelanggan, No Langganan, CUST-XXXXXX

**Layanan Pelanggan**:
Entitas langganan aktif yang menghubungkan seorang pelanggan dengan paket layanan internet tertentu, router gateway, kredensial PPP, dan masa aktif.
_Avoid_: Subscription, Akun Internet, Koneksi

**Site ID**:
Pengenal unik titik instalasi layanan pelanggan (format `SITE-XXXXXXXX`).
_Avoid_: Service ID, Lokasi ID

**Paket Layanan**:
Entitas katalog paket internet ISP yang menentukan profil bandwidth, tarif, dan masa aktif (hari/bulan).
_Avoid_: Package, Product, Paket Data

**Profil Bandwidth**:
Konfigurasi limit kecepatan transfer data (max limit upload/download, burst rate, priority) dalam satuan standar Mbps untuk di-provision ke MikroTik RouterOS.
_Avoid_: Speed Profile, Paket Bandwidth, Konfigurasi Kbps (Gunakan Mbps)

**Router**:
Entitas perangkat MikroTik RouterOS sebagai pengendali layanan dan bandwidth, diakses melalui RouterOS API (port 8728).
_Avoid_: Switch, Gateway Umum

**IP Pool**:
Blok alokasi alamat IP (Network, CIDR, Range IP) yang terikat pada Router untuk distribusi IP pelanggan.
_Avoid_: Subnet Bebas, DHCP Range

**Invoice**:
Dokumen tagihan pembayaran resmi atas layanan internet pelanggan dengan format penomoran `INV-YYYYMM-NNNNNN`.
_Avoid_: Tagihan Bebas, Kuitansi (sebelum dibayar), Bill

**Pembayaran**:
Catatan transaksi penerimaan dana atas sebuah invoice yang memicu perpanjangan masa aktif layanan secara otomatis.
_Avoid_: Transaksi Kasar, Setoran

**Promo**:
Program diskon (nominal / persentase) atau bonus durasi yang dapat diaplikasikan pada penerbitan invoice.
_Avoid_: Voucher Bebas, Potongan Informal
