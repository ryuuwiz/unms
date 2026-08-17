# Standardisasi Satuan Mbps pada Profil Bandwidth dan Sinkronisasi RouterOS

## Status
Accepted

## Context
Sebelumnya, modul `ProfilBandwidth` (`profil_bandwidth`) menyimpan nilai kecepatan upload/download (`max_limit_tx`, `max_limit_rx`, dan parameter burst) dalam satuan **Kbps** (misalnya `10240` atau `20000`). Hal ini menimbulkan inkonsistensi dengan modul komersial [ADR 0003](file:///c:/Ryu/Projects/unms/docs/adr/0003-package-bandwidth-modeling-and-status-lifecycle.md) yang menggunakan satuan **Mbps** (`download_speed_mbps`, `upload_speed_mbps`), serta mempersulit input bagi staf NOC dan interpretasi visual pada UI.

## Decisions
1. **Penyimpanan Integer Mbps Murni (Pure Mbps Storage)**:
   - Seluruh kolom kecepatan pada tabel `profil_bandwidth` (`max_limit_tx`, `max_limit_rx`, `burst_rate_tx`, `burst_rate_rx`, `burst_threshold_tx`, `burst_threshold_rx`, `limit_rate_tx`, `limit_rate_rx`) menggunakan tipe `unsignedInteger` dalam satuan **Mbps**.
2. **Cakupan Satuan Menyeluruh**:
   - Perubahan satuan ke Mbps diberlakukan seragam untuk parameter kecepatan utama (Max Limit), batas lonjakan (Burst Rate & Burst Threshold), serta jaminan kecepatan minimum (Limit Rate/CIR).
3. **Format Sinkronisasi MikroTik RouterOS**:
   - String rate-limit yang digenerate untuk MikroTik RouterOS API / PPP profile menggunakan suffix `M` (Mega), misalnya `"20M/20M"` untuk max-limit dan `"30M/30M 15M/15M 16/16 8 5M/5M"` untuk konfigurasi burst lengkap.
4. **Pembaruan Seeder, Factory, dan Test Suite**:
   - Seluruh database seeders, factory, dan automated test suite diperbarui menggunakan angka Mbps bulat standar ISP (`10`, `20`, `50`, `100`).
5. **Validasi Hierarki Logika Burst RouterOS**:
   - Form Livewire menerapkan validasi terstruktur (`burst_rate >= max_limit`, `burst_threshold <= burst_rate`, dan `limit_rate <= max_limit`) untuk mencegah kesalahan input staf NOC sebelum profil di-push ke RouterOS.
6. **Helper Enkapsulasi Rate-Limit RouterOS pada Model**:
   - Model `ProfilBandwidth` menyediakan method `routerOsRateLimit(): string` yang menghasilkan format string lengkap rate-limit standar MikroTik baik untuk profil standar maupun burst.

## Consequences
- Memangkas logika konversi pembagian/perkalian 1000/1024 pada model dan presentasi Blade.
- UI form dan tabel profil bandwidth menjadi lebih ringkas dan konsisten dengan seluruh katalog paket ISP.
- Skrip queue RouterOS lebih bersih dan mudah dibaca oleh Network Engineer pada Winbox / CLI MikroTik.
