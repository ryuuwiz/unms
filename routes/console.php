<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoice:generate')->dailyAt('01:00')->onOneServer();
Schedule::command('invoice:cek-kadaluarsa')->dailyAt('02:00')->onOneServer();
Schedule::command('layanan:cek-isolir')->dailyAt('02:30')->onOneServer();

// Tenggat Pembayaran Invoice Pertama (H+1): dicek tiap jam (bukan ikut jadwal harian
// di atas) supaya isolir benar-benar terjadi mendekati 1x24 jam, bukan sampai ~2 hari.
Schedule::command('layanan:cek-tunggakan-pertama')->hourly()->onOneServer();

// Periodic Fast Auto-Recovery for IP Pools, Profiles & PPP Secrets (In-Memory Diff via Queue mikrotik-low)
Schedule::command('mikrotik:provisi-router --async')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->onOneServer()
    ->runInBackground();

// Master Nightly Reconciliation & Orphaned Secrets Cleanup (Asynchronous per-router queue)
Schedule::command('mikrotik:provisi-router --async --clean-orphans')
    ->dailyAt('03:00')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->runInBackground();

Schedule::command('xendit:cek-va-expired')->hourly()->onOneServer();

// Sweeper rekonsiliasi pembayaran dua arah: dispatch ulang webhook mandek + polling
// gateway untuk transaksi pending -- jaring pengaman agar pembayaran tidak pernah
// tertahan diam-diam jika webhook hilang atau job antrean gagal total.
Schedule::command('pembayaran:rekonsiliasi')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->onOneServer();

// Periodic Health Check / System Resource Ping (Non-blocking queue)
Schedule::command('mikrotik:ping')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
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
