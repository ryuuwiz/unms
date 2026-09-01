# ADR 0033: Resolusi Local & Remote Address PPP Secret Berbasis IP Pool Router Terhubung

**Status**: Accepted  
**Date**: 2026-09-01  

## Konteks

Pada integrasi MikroTik RouterOS PPPoE Server, setiap akun PPP Secret membutuhkan parameter `local-address` (gateway router) dan `remote-address` (nama pool IP atau IP statis).
Sebelumnya, terdapat inkonsistensi di mana `resolveRemoteAddress()` mengembalikan `null` untuk koneksi PPPoE dinamis, dan validasi form belum secara ketat memastikan bahwa `ip_pool_id` mutlak harus dimiliki oleh `router_id` layanan yang bersangkutan.

## Keputusan

1. **Resolusi `remote-address` Dinamis & Statis**:
   - Untuk koneksi PPPoE dinamis (`jenis_koneksi == 'pppoe'`), `resolveRemoteAddress()` mengembalikan nama IP Pool (`$this->ipPool?->nama_pool`).
   - Untuk koneksi IP Statis (`jenis_koneksi == 'ip_static'`), `resolveRemoteAddress()` mengembalikan alamat IPv4 literal (`$this->ip_static`).

2. **Resolusi `local-address` (Gateway Tunnel)**:
   - Mengambil IP host pertama dari network IP Pool terkait (`$this->ipPool?->getGatewayAddress()`, contoh: network `10.0.0.0/24` menghasilkan gateway `10.0.0.1`).
   - Jika koneksi IP Statis tanpa relasi IP Pool eksplisit, gateway dihitung dari subnet IP statis tersebut.

3. **Integritas Relasi Router & IP Pool**:
   - Validasi ketat diterapkan pada form create/edit dan model layer (`Rule::exists('ip_pool', 'id')->where('router_id', $this->router_id)`).
   - Livewire dropdown secara reaktif memfilter daftar IP Pool hanya milik router terpilih dan me-reset nilai `ip_pool_id` saat router berubah.

4. **Guarding & Auto-Ensure pada `MikrotikService`**:
   - Provisi PPPoE memiliki *strict guard*: melempar `MikrotikException` jika layanan PPPoE tidak memiliki relasi IP Pool yang valid.
   - Saat memprovisi PPP Secret, `MikrotikService` memastikan IP Pool sudah terdaftar di RouterOS (`/ip/pool`) sebelum akun secret dibuat.
   - Perintah rekonsiliasi periodik (`mikrotik:recover-ppp`) membandingkan `local-address` dan `remote-address` aktual di RouterOS dengan perhitungan UNMS dan melakukan *auto-healing* jika terjadi drift.

## Konsekuensi

- Kolom `Local Address` dan `Remote Address` pada tabel `/ppp/secret` RouterOS selalu terisi valid sesuai pool subnet router masing-masing pelanggan.
- Menghilangkan risiko tabrakan IP dan inkonsistensi alokasi cross-router.
- Memerlukan pembaruan unit & feature test untuk memastikan assertion `resolveRemoteAddress()` memeriksa nama pool saat koneksi dinamis.
