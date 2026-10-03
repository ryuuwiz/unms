<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoice:generate')->dailyAt('01:00')->onOneServer()->sentryMonitor();
Schedule::command('invoice:cek-kadaluarsa')->dailyAt('02:00')->onOneServer();
Schedule::command('layanan:cek-isolir')->dailyAt('02:30')->onOneServer()->sentryMonitor();

// Tugas billing kritis memakai sentryMonitor(): Sentry Crons memberi alert saat tugas tidak jalan sama
// sekali (scheduler mati), hal yang tidak bisa dideteksi Log Tugas Terjadwal dari dalam aplikasi.

// Tenggat Pembayaran Invoice Pertama (H+1): dicek tiap jam (bukan ikut jadwal harian
// di atas) supaya isolir benar-benar terjadi mendekati 1x24 jam, bukan sampai ~2 hari.
Schedule::command('layanan:cek-tunggakan-pertama')->hourly()->onOneServer();

// Periodic Fast Auto-Recovery for IP Pools, Profiles & PPP Secrets (In-Memory Diff via Queue mikrotik-low)
Schedule::command('mikrotik:provisi-router --async')
    ->everySecond()
    ->withoutOverlapping(15)
    ->onOneServer()
    ->runInBackground();

// Master Nightly Reconciliation & Orphaned Secrets AUDIT (Asynchronous per-router queue).
// Orphan hanya diaudit, tidak pernah dihapus: tanpa komentar penanda, orphan tidak bisa dibedakan dari
// secret buatan NOC (ADR-0063).
Schedule::command('mikrotik:provisi-router --async --audit-orphans')
    ->dailyAt('03:00')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->runInBackground();

// Provisi Cadangan: layanan yang belum terprovisi dan tidak dicoba job antrean 5 menit terakhir diantrekan ulang.
Schedule::command('mikrotik:provisi-tertunda')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('pembayaran:cek-kedaluwarsa')->hourly()->onOneServer();

// Sweeper rekonsiliasi pembayaran dua arah: dispatch ulang webhook mandek + polling
// gateway untuk transaksi pending -- jaring pengaman agar pembayaran tidak pernah
// tertahan diam-diam jika webhook hilang atau job antrean gagal total.
Schedule::command('pembayaran:rekonsiliasi')
    ->everyFiveMinutes()
    ->withoutOverlapping(15)
    ->onOneServer()
    ->sentryMonitor();

// Ping Router (CONTEXT.md): router Offline/Tidak Diketahui tiap 5 menit, router Online tiap 2 jam
// (penyaringan di mikrotik:ping). Ketersediaan jaringan dipantau PRTG, bukan UNMS.
Schedule::command('mikrotik:ping')
    ->everyFiveMinutes()
    ->withoutOverlapping(1)
    ->onOneServer()
    ->runInBackground();

// Tugas sub-menit dijalankan berulang oleh schedule:run per menit (docker/supervisor.d/schedule.conf).
// Pemantauan Sesi PPP (ADR-0070): perubahan sesi didorong ke Detail Pelanggan lewat Reverb.
Schedule::command('mikrotik:pantau-sesi')
    ->everyTenSeconds()
    ->withoutOverlapping(1)
    ->onOneServer()
    ->runInBackground();

// Pengingat Tagihan WhatsApp Otomatis (Setiap jam memeriksa aturan aktif)
Schedule::command('invoice:kirim-pengingat')->hourly()->onOneServer();
Schedule::command('wa:proses-antrian')->everyFiveMinutes()->onOneServer();

// Horizon metrics snapshot for throughput and queue wait time dashboard
Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();

// Alerts super_admin/noc if Horizon is down, paused, or has completed no job
// in 15 minutes -- catches a wedged worker that a plain HTTP HEALTHCHECK on
// the web process can't see (Horizon shares the container with Caddy/PHP-FPM).
Schedule::command('horizon:monitor-health')
    ->everyFiveMinutes()
    ->onOneServer()
    ->environments('production');

// Hapus Log Tugas Terjadwal lebih dari 30 hari (model Prunable).
Schedule::command('model:prune')->daily()->onOneServer();

// hapus log Telescope lebih dari 30 hari (model Prunable). Telescope log bisa sangat besar, jadi hapus
// lebih sering daripada Log Tugas Terjadwal. Telescope log juga tidak bisa dihapus via
Schedule::command('telescope:prune')->daily()->environments('local')->onOneServer();

// Simpan output setiap tugas ke storage/logs agar CatatLogTugasTerjadwal bisa melampirkan
// ekor output saat tugas gagal. Harus tetap di baris paling akhir file ini.
foreach (Schedule::events() as $event) {
    $event->storeOutput();
}

// Failover Penyimpanan: kembalikan media yang sempat disimpan lokal ke S3 (ADR-0068).
Schedule::command('media:sinkron-s3')->everyTenMinutes()->onOneServer()->withoutOverlapping();
