<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoice:generate')->dailyAt('01:00')->onOneServer();
Schedule::command('invoice:cek-kadaluarsa')->dailyAt('02:00')->onOneServer();
Schedule::command('layanan:cek-isolir')->dailyAt('02:30')->onOneServer();

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

// Periodic Health Check / System Resource Ping (Non-blocking queue)
Schedule::command('mikrotik:ping')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->runInBackground();

// Pengingat Tagihan WhatsApp Otomatis (Setiap jam memeriksa aturan aktif)
Schedule::command('invoice:kirim-pengingat')->hourly()->onOneServer();
Schedule::command('wa:proses-antrian')->everyFiveMinutes()->onOneServer();
