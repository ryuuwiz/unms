<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoice:generate')->dailyAt('01:00');
Schedule::command('invoice:cek-kadaluarsa')->dailyAt('02:00');
Schedule::command('layanan:cek-isolir')->dailyAt('02:30');
Schedule::command('xendit:cek-va-expired')->hourly();
Schedule::command('mikrotik:ping')->everyFiveMinutes();
