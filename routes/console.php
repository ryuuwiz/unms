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
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->onOneServer()
    ->runInBackground();

// Master Nightly Reconciliation & Orphaned Secrets AUDIT (Asynchronous per-router queue).
// Sengaja --audit-orphans, bukan --clean-orphans: penjadwal tidak pernah menghapus PPP Secret karena ada
// secret buatan NOC yang tidak terdaftar di billing. Penghapusan orphan hanya lewat CLI eksplisit.
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

Schedule::command('xendit:cek-va-expired')->hourly()->onOneServer();

// Sweeper rekonsiliasi pembayaran dua arah: dispatch ulang webhook mandek + polling
// gateway untuk transaksi pending -- jaring pengaman agar pembayaran tidak pernah
// tertahan diam-diam jika webhook hilang atau job antrean gagal total.
Schedule::command('pembayaran:rekonsiliasi')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->onOneServer()
    ->sentryMonitor();

// Ping router tiap 10 dtk: online<->offline terdeteksi < 10 dtk dan memicu notifikasi NOC + recovery.
// Tugas sub-menit dijalankan berulang oleh schedule:run per menit (docker/supervisor.d/schedule.conf).
Schedule::command('mikrotik:ping')
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
Schedule::command('horizon:monitor-health')->everyFiveMinutes()->onOneServer();

// Hapus Log Tugas Terjadwal lebih dari 30 hari (model Prunable).
Schedule::command('model:prune')->daily()->onOneServer();

// Simpan output setiap tugas ke storage/logs agar CatatLogTugasTerjadwal bisa melampirkan
// ekor output saat tugas gagal. Harus tetap di baris paling akhir file ini.
foreach (Schedule::events() as $event) {
    $event->storeOutput();
}
