<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoice:generate')->dailyAt('01:00');
Schedule::command('invoice:cek-kadaluarsa')->dailyAt('02:00');
Schedule::command('layanan:cek-isolir')->dailyAt('02:30');

// Master Nightly Reconciliation (Provisi Lengkap, IP Pool, Binary Bps & Pembersihan Orphaned Secret)
Schedule::command('mikrotik:provisi-router --clean-orphans')
    ->dailyAt('03:00')
    ->withoutOverlapping(60)
    ->runInBackground();

// Periodic Fast Auto-Recovery for PPP Secrets & Profiles (Asynchronous per-router queue)
Schedule::command('mikrotik:recover-ppp --async')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->runInBackground();

Schedule::command('xendit:cek-va-expired')->hourly();

// Periodic Health Check / System Resource Ping (Non-blocking queue)
Schedule::command('mikrotik:ping')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground();

// Pengingat Tagihan WhatsApp Otomatis (Setiap jam memeriksa aturan aktif)
Schedule::command('invoice:kirim-pengingat')->hourly();
Schedule::command('wa:proses-antrian')->everyFiveMinutes();
