# UNMS Domain Context

Ubiquiti & ISP Network Management System (UNMS) staff application context and ubiquitous language.

## Language

**Menu Utama**:
Kelompok navigasi utama untuk fitur operasional harian yang dapat diakses oleh staff sesuai hak aksesnya.
_Avoid_: Platform, Main Navigation, Navigasi

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

**Kode Pelanggan**:
Pengenal unik terstandarisasi sistem untuk setiap pelanggan (format `CUST-XXXXXX`).
_Avoid_: Customer ID, Nomor Pelanggan, No Langganan

**Alamat Instalasi**:
Titik lokasi fisik tempat jaringan dan perangkat ISP dipasang, dilengkapi alamat teks dan koordinat geografis (lat/lng).
_Avoid_: Lokasi Pasang, Installation Site

**Status Pelanggan**:
Status operasional akun master pelanggan (`Active` / `Inactive`).
_Avoid_: Kondisi, State

