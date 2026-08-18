# Standardisasi Satuan Mbps pada Profil Bandwidth dan Sinkronisasi RouterOS

## Status
Accepted

## Context
Sebelumnya, modul `ProfilBandwidth` (`profil_bandwidth`) menyimpan nilai kecepatan upload/download (`max_limit_tx`, `max_limit_rx`, dan parameter burst) dalam satuan **Kbps** (misalnya `10240` atau `20000`). Hal ini menimbulkan inkonsistensi dengan modul komersial [ADR 0003](file:///c:/Ryu/Projects/unms/docs/adr/0003-package-bandwidth-modeling-and-status-lifecycle.md) yang menggunakan satuan **Mbps** (`download_speed_mbps`, `upload_speed_mbps`), serta mempersulit input bagi staf NOC dan interpretasi visual pada UI.

## Decisions
1. **Penyimpanan Integer Mbps Murni (Pure Mbps Storage)**:
   - Seluruh kolom kecepatan pada tabel `profil_bandwidth` (`max_limit_tx`, `max_limit_rx`, `burst_rate_tx`, `burst_rate_rx`, `burst_threshold_tx`, `burst_threshold_rx`, `limit_rate_tx`, `limit_rate_rx`) menggunakan tipe `unsignedInteger` dalam satuan **Mbps**.
2. **Cakupan Satuan Menyeluruh**:
   - Perubahan satuan ke Mbps diberlakukan seragam untuk parameter kecepatan utama (Max Limit), batas lonjakan (Burst Rate & Burst Threshold), durasi burst (detik), serta batas kecepatan limit rate.
3. **Format Sinkronisasi MikroTik RouterOS**:
   - Format rate-limit RouterOS mengikuti spesifikasi `data_unms.md`:
     - Non-burst: format ringkas `max_limit_tx M/max_limit_rx M` (misal `"20M/20M"`).
     - Burst: format string lengkap `max_limit burst_rate burst_threshold burst_time priority limit_rate`.
4. **Pembaruan Seeder, Factory, dan Test Suite**:
   - Seluruh database seeders, factory, dan automated test suite diperbarui menggunakan angka Mbps bulat standar ISP (`10`, `20`, `50`, `100`).
5. **Validasi Hierarki Logika Burst RouterOS**:
   - Form Livewire menerapkan validasi terstruktur (`burst_rate >= max_limit`, `burst_threshold <= burst_rate`, dan `limit_rate <= max_limit`). Konfigurasi burst dan limit-rate dikelompokkan dalam toggle Burst opsional sesuai `data_unms.md`.
6. **Helper Enkapsulasi Model dan Konvensi Label TX / RX**:
   - Model `ProfilBandwidth` menyediakan `labelKecepatan(): string` dengan format konsisten **TX / RX** (misal `"20/10 Mbps"` atau `"20 Mbps (1:1)"`), serta `routerOsRateLimit(): string` yang menghasilkan format string yang kompatibel dengan RouterOS.

## Consequences
- Memangkas logika konversi pembagian/perkalian 1000/1024 pada model dan presentasi Blade.
- Memperbaiki bug data 10240 Mbps yang berasal dari data lama di database.
- Tampilan UI dan label kecepatan konsisten dalam format TX / RX di seluruh aplikasi.
- Skrip queue RouterOS lebih bersih dan mudah dibaca oleh Network Engineer pada Winbox / CLI MikroTik.
