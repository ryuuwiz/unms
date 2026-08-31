# ADR 0030: Arsitektur Pemisahan Antrean Horizon, Optimasi Ping MikroTik, dan Fast In-Memory PPP Reconciliation

## Konteks
Operasi manajemen MikroTik RouterOS di UNMS mencakup dua kategori beban kerja dengan karakteristik yang berbeda:
1. **Real-time Lifecycle Provisioning (Prioritas Tinggi)**: Penambahan akun PPPoE baru dari Livewire, perubahan paket layanan (upgrade/downgrade), isolir otomatis tagihan kadaluarsa, dan pembukaan isolir setelah pembayaran webhook. Operasi ini membutuhkan eksekusi instan (<1 detik) tanpa latensi antrean.
2. **Periodic Background Maintenance (Prioritas Rendah/Background)**: Health check / ping status berkala setiap 5 menit, sinkronisasi IP Pool & Profil Bandwidth massal, dan validasi/rekonsiliasi seluruh akun PPP Secret di RouterOS terhadap database UNMS.

Sebelumnya, seluruh operasi ini berbagi antrean tunggal (`mikrotik`) di dalam satu supervisor pool Horizon. Hal ini memicu permasalahan:
- **Queue Starvation & Latency Cascade**: Ketika satu atau lebih router mati/offline, socket timeout (15s), attempt ganda (2x), dan mekanisme retry (2x) menyandera proses queue worker selama >60 detik per router. Aksi provisi pelanggan terblokir di belakang antrean ping router offline.
- **Redundant Database Logging**: Health-check rutin menulis ribuan entri log setiap hari ke `mikrotik_job_logs` meskipun tidak ada perubahan status.
- **Monolithic Scheduler & Query Round-trip Loop**: Command `mikrotik:recover-ppp` berjalan sekuensial dan mengirim query nested `/ppp/profile/print` serta `/ppp/secret/print` berulang per pelanggan, menimbulkan *CPU spike* di RouterOS dan memakan waktu menit demi menit.

## Keputusan yang Diambil

1. **Pemisahan Antrean Horizon (Queue Segregation)**:
   - **`mikrotik-high`**: Antrean prioritas tinggi terisolasi untuk operasi provisi instan (`ProvisionPppoeAccountJob`, `UpdatePppoeProfileJob`, `DisablePppoeAccountJob`, `EnablePppoeAccountJob`, `CleanupPppSecretOnOldRouterJob`). Dijalankan oleh `supervisor-high` dengan alokasi proses terisolasi tanpa jeda.
   - **`mikrotik-low`**: Antrean background untuk operasi berkala (`PingRouterJob`, `RecoverPppRouterJob`, `SyncBandwidthProfileToRoutersJob`, `SyncIpPoolToRouterJob`, `ProvisionRouterJob`). Dijalankan oleh `supervisor-low` bersama `wa-blast` dan `default` dengan auto-scaling.

2. **Strict Socket Timeout & Non-Blocking Ping**:
   - Health check ping (`testConnection`) menggunakan `timeout = 3s`, `socket_timeout = 3s`, `attempts = 1`, dan `tries = 1` pada `PingRouterJob` tanpa retry antrean.
   - Jika router offline, router langsung ditandai `Offline` dalam 3 detik dan melepaskan worker seketika.
   - Log `MikrotikJobLog` hanya dicatat saat terjadi transisi status (misal Online ↔ Offline) atau error, menghemat ribuan operasi I/O database.
   - **Auto-Recovery on Reconnect**: Saat `PingRouterJob` mendeteksi router bertransisi dari *Offline* ke *Online*, sistem secara otomatis memicu `RecoverPppRouterJob` untuk memulihkan dan memvalidasi konfigurasi router tersebut.

3. **Fast In-Memory Diffing Reconciliation**:
   - `RecoverPppRouterJob` mengambil seluruh PPP Secret di RouterOS dalam 1x bulk query read (`/ppp/secret/print`), lalu mencocokkannya secara instan di memori RAM PHP dengan data `LayananPelanggan`.
   - Mutasi data (`/ppp/secret/set` atau `/ppp/secret/add`) hanya dikirim untuk entri yang tidak sinkron, menggunakan `.id` yang telah terpetakan di memori tanpa query baca per akun. Jika data sudah sinkron, **0 query tulis dikirim ke MikroTik**.
   - Scheduler menjalankan `mikrotik:recover-ppp --async` setiap 15 menit (`everyFifteenMinutes()`), mendispatch job per-router secara independen ke antrean `mikrotik-low` untuk dieksekusi paralel.

## Konsekuensi
- **Provisi Real-Time Instan**: Aksi staf di antarmuka Livewire dan automasi billing tidak pernah terhambat oleh antrean background atau router lain yang sedang offline.
- **Efisiensi Beban CPU MikroTik**: Mencegah lonjakan CPU 100% pada MikroTik berkat pengurangan query round-trip dan eliminasi duplikasi koneksi bersamaan.
- **Database Bersih**: Mencegah pembengkakan tabel `mikrotik_job_logs` dari health-check rutin yang berhasil.
- **Ketahanan Jaringan (*Resilience*)**: Seluruh akun PPP Secret tervalidasi dan tersinkronisasi otomatis setiap 15 menit dan instan saat router pulih dari offline.
