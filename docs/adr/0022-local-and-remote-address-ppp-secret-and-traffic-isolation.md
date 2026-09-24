# ADR 0022: Manajemen Local & Remote Address PPP Secret dan Isolasi Jalur Up To vs Dedicated

**Status**: Superseded oleh ADR-0051 (perilaku `local/remote-address` PPP Secret; isolasi jalur/segmentasi tetap berlaku)  
**Date**: 2026-08-25  

## Konteks

Pada integrasi MikroTik RouterOS PPPoE Server, setiap akun PPP Secret membutuhkan dua parameter routing penting:
1. `local-address` — Alamat IP Gateway sisi router yang diberikan ke endpoint PPP client.
2. `remote-address` — Alamat IP tujuan / Pool IP yang dialokasikan ke pelanggan.

Sebelumnya di UNMS:
- Field `local-address` tidak dikirim oleh UNMS, menyebabkan PPP Secret di RouterOS tidak memiliki gateway eksplisit dan bergantung pada profile default yang berpotensi ambigu.
- Field `remote-address` hanya dikirim jika pelanggan memiliki IP Statis, sehingga pelanggan dinamis tidak memiliki `remote-address` di RouterOS.
- Paket internet Up To (Broadband/Shared) dan Dedicated (1:1 CIR) belum terisolasi jalurnya pada master seeder, sehingga berisiko menumpuk di subnet pool yang sama tanpa segmentasi gateway QoS.

## Keputusan

### 1. Standarisasi `local-address` (Gateway) dan `remote-address` di Model Layer
- Di model [IpPool](file:///c:/Ryu/Projects/unms/app/Models/IpPool.php), method `getGatewayAddress(): string` menghitung IP host pertama dari `ip_network` (contoh: `10.0.0.0/24` $\rightarrow$ `10.0.0.1`, `10.0.1.0/24` $\rightarrow$ `10.0.1.1`).
- Di model [LayananPelanggan](file:///c:/Ryu/Projects/unms/app/Models/LayananPelanggan.php):
  - `resolveLocalAddress(): ?string`: Mengembalikan gateway dari `ipPool` atau subnet gateway dari `ip_static`.
  - `resolveRemoteAddress(): ?string`: Mengembalikan IP Statis literal (`ip_static`) jika ada, atau nama IP Pool (`ipPool->nama_pool`) untuk PPPoE dinamis.

### 2. Segmentasi Jalur & Hierarki 3-Tingkat QoS
- **Jalur Residensial Up To (Broadband / Shared, Priority 7–8)**:
  - Menggunakan IP Pool `Pool-Rumah` (Subnet `10.0.0.0/24`, Gateway / Local Address: `10.0.0.1`, Priority QoS: 8/8).
  - Profil Bandwidth: Memiliki burst rate, threshold, dan limit-at (misal `Profile-Home-UpTo-10M`, `Profile-Home-UpTo-20M`, `Profile-Home-UpTo-50M`).
  - Terikat ke paket-paket broadband ekonomis.
- **Jalur Residensial 1:1 (Dedicated Home / Gamer / Streamer, Priority 3–5)**:
  - Menggunakan IP Pool `Pool-Rumah` (Subnet `10.0.0.0/24`, Gateway / Local Address: `10.0.0.1`).
  - Profil Bandwidth: Flat 1:1 CIR tanpa burst/contention (`Profile-Home-Ded-20M` Priority 5, `Profile-Gamer-Ded-50M` Priority 4, `Profile-Streamer-Ded-100M` Priority 3).
  - Menjamin latensi rendah dan kestabilan upload/download untuk gaming & streaming di rumah.
- **Jalur Bisnis / Corporate 1:1 (Enterprise Dedicated, Priority 1–2)**:
  - Menggunakan IP Pool `Pool-Bisnis` (Subnet `10.0.1.0/24`, Gateway / Local Address: `10.0.1.1`, Priority QoS 1-2) atau IP Statis dedicated (`10.0.1.X`).
  - Profil Bandwidth: CIR 1:1 dengan jaminan SLA tertinggi (`Profile-Biz-50M`, `Profile-Biz-100M`).

### 3. Eksekusi Provisi & Rekonsiliasi Idempoten di `MikrotikService`
- `createOrUpdatePppoeSecret()` selalu menyetel `local-address` dan `remote-address` saat membuat (`/ppp/secret/add`) atau memperbarui (`/ppp/secret/set`) akun secret di RouterOS.
- `autoRecoverPppSecrets()` memeriksa keberadaan drift konfigurasi pada `local-address` dan `remote-address`, memicu auto-repair jika parameter di RouterOS tidak sesuai dengan alokasi UNMS.

### 4. Standarisasi Seeder Pelanggan (`PelangganSeeder`)
- [PelangganSeeder.php](file:///c:/Ryu/Projects/unms/database/seeders/PelangganSeeder.php) secara otomatis memetakan pelanggan perumahan ke `Pool-Rumah` dan pelanggan bisnis ke `Pool-Bisnis` / IP Statis dengan mekanisme *fallback resilience* ke [IpPoolSeeder](file:///c:/Ryu/Projects/unms/database/seeders/IpPoolSeeder.php).

## Konsekuensi

- **Positif**:
  - Tampilan Winbox PPP Secrets terisi lengkap: kolom `Local Address` dan `Remote Address` selalu valid untuk semua pelanggan.
  - Isolasi traffic antara pelanggan Up To dan Dedicated terjamin di level router gateway dan subnetting.
  - Logika alokasi IP berada terpusat di layer Model Eloquent (*single source of truth*).
- **Pertimbangan**:
  - Router harus memiliki konfigurasi IP Pool di RouterOS yang namanya cocok dengan nama pool di UNMS (dijamin oleh `SyncIpPoolToRouterJob` dan `IpPoolObserver`).
