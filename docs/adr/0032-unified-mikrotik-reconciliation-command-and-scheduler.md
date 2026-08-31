# ADR 0032: Unified MikroTik Router Reconciliation Command and Asynchronous Scheduler Pipeline

## Konteks
Sebelumnya, sistem GOBILLING (UNMS) memiliki dua proses konsol terpisah di `routes/console.php` untuk memelihara sinkronisasi Router MikroTik:
1. `mikrotik:provisi-router --clean-orphans`: Dijalankan terjadwal harian pukul 03:00 subuh secara *synchronous sekuensial* di CLI untuk provisi penuh IP Pool, Profil Bandwidth, PPP Secret, dan pembersihan *orphaned secrets*.
2. `mikrotik:recover-ppp --async`: Dijalankan terjadwal berkala setiap 15 menit secara *asynchronous* melalui antrean `mikrotik-low` di Horizon untuk melakukan pemulihan IP Pool, Profil Bandwidth, dan *Rekonsiliasi Cepat PPP In-Memory*.

Hal ini menimbulkan redundansi logika dan potensi kelemahan performa:
- Redundansi implementasi logika provisi dan recovery router di dua command terpisah.
- `mikrotik:provisi-router` di scheduler sebelumnya dieksekusi secara sekuensial di terminal cron sehingga jika ada router yang lambat/timeout, proses scheduler master berisiko terhambat (*hanging*).
- Kurangnya keterpaduan opsi antrean pada master command.

## Keputusan yang Diambil

1. **Unifikasi Command Kanonikal (`mikrotik:provisi-router`)**:
   - `mikrotik:provisi-router` dijadikan sebagai Artisan command kanonikal terpadu dengan signature lengkap:
     ```bash
     php artisan mikrotik:provisi-router {--router=} {--force} {--clean-orphans} {--async}
     ```
   - Opsi `--async` mendispatch instance `RecoverPppRouterJob` per router secara paralel ke antrean `mikrotik-low`.

2. **Backward-Compatible Proxy (`mikrotik:recover-ppp`)**:
   - Perintah `mikrotik:recover-ppp` tetap dipertahankan sebagai jembatan / alias transparan yang kompatibel penuh untuk memastikan script otomatisasi atau referensi eksternal yang sudah ada tidak terputus.

3. **Asynchronous Non-Blocking Console Scheduler with Multi-Replica Locking (`onOneServer`)**:
   - Scheduler di `routes/console.php` distandarisasi untuk selalu menjalankan proses secara *asynchronous* berbasis queue `mikrotik-low`:
     - **Fast Auto-Recovery (Setiap 15 Menit)**:
       ```php
       Schedule::command('mikrotik:provisi-router --async')
           ->everyFifteenMinutes()
           ->withoutOverlapping(15)
           ->onOneServer()
           ->runInBackground();
       ```
     - **Master Nightly Reconciliation & Orphan Cleanup (Harian 03:00 Subuh)**:
       ```php
       Schedule::command('mikrotik:provisi-router --async --clean-orphans')
           ->dailyAt('03:00')
           ->withoutOverlapping(60)
           ->onOneServer()
           ->runInBackground();
       ```
   - Opsi `->onOneServer()` memastikan bahwa dalam deployment multi-server / multi-replica (Kubernetes / ECS / Fly.io / Load Balancer), hanya **1 replica** yang memicu cron trigger di setiap siklus.

4. **Multi-Replica Queue Concurrency & Distributed Lock Resilience**:
   - **Queue Level**: Seluruh job eksekusi router memanfaatkan kontrak `ShouldBeUnique` dengan kunci isolasi per-router (`uniqueId = router->id`, `uniqueFor = 300s`) pada antrean background `mikrotik-low` guna mencegah duplikasi job di cluster worker.
   - **Worker Level (Atomic Distributed Lock)**: Eksekusi `RecoverPppRouterJob` dibungkus dengan `Cache::lock("mikrotik:router:{$router->id}", 120)` untuk mencegah benturan koneksi API socket TCP RouterOS port 8728 antar worker di replica yang berbeda.
   - **Real-Time Pre-Deletion Verification**: Pada pembersihan *orphaned secrets* (`cleanOrphanedPppSecrets`), sistem melakukan query real-time database `LayananPelanggan::where('router_id', $router->id)->where('ppp_username', $name)->exists()` tepat sebelum perintah `/ppp/secret/remove` dieksekusi, mencegah penghapusan akun pelanggan yang baru mendaftar di replica lain saat proses komparasi sedang berlangsung.
   - **Audit Trail & Notifikasi**: Kegagalan eksekusi job otomatis mencatat audit trail di `MikrotikJobLog` dan mengirim notifikasi kegagalan (`MikrotikJobFailedNotification`) ke pengguna dengan peran `super_admin` dan `noc`.

## Konsekuensi
- **Multi-Replica Safe**: Zero race condition dan zero duplicate cron executions pada deployment terdistribusi (Docker / Kubernetes / Multi-Server).
- **Non-blocking Scheduler**: Proses cron utama tidak pernah terblokir oleh latency jaringan router MikroTik.
- **Konsistensi Pipeline**: Logika rekonsiliasi router tersentralisasi dan mudah dipelihara.
- **Beban CPU Efisien**: Pembersihan orphaned secrets hanya dilakukan pada jam subuh (03:00) sementara pemulihan cepat in-memory berjalan ringan setiap 15 menit.
- **Zero Breaking Changes**: Seluruh test, UI caller, dan legacy command tetap bekerja secara transparan.
