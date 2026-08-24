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

// Fast Sub-Minute Auto-Recovery (Pemulihan Instan Secret & Profil Tanpa Menghapus Orphan)
Schedule::command('mikrotik:recover-ppp')
    ->everyFiveSeconds()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('xendit:cek-va-expired')->hourly();
Schedule::command('mikrotik:ping')->everyTwentySeconds();
