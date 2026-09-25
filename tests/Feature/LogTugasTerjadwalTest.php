<?php

use App\Enums\StatusTugasTerjadwal;
use App\Enums\UserStatus;
use App\Livewire\TugasTerjadwal\Index;
use App\Models\LogTugasTerjadwal;
use App\Models\User;
use App\Notifications\TugasTerjadwalGagalNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function tugasTerjadwal(string $perintah = 'invoice:generate'): Event
{
    return app(Schedule::class)->command($perintah)->daily();
}

function userDenganPeran(string $peran): User
{
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole($peran);

    return $user;
}

test('tugas harian yang berhasil dicatat beserta durasinya', function () {
    $this->freezeTime();
    $task = tugasTerjadwal();
    $task->exitCode = 0;

    event(new ScheduledTaskFinished($task, 2.5));

    $log = LogTugasTerjadwal::sole();
    expect($log->perintah)->toBe('invoice:generate')
        ->and($log->status)->toBe(StatusTugasTerjadwal::Berhasil)
        ->and($log->durasi_detik)->toBe('2.50')
        ->and($log->mulai_at->lessThan($log->selesai_at))->toBeTrue()
        ->and($log->output)->toBeNull();
});

test('tugas dengan exit code bukan nol dicatat gagal beserta ekor output', function () {
    Notification::fake();
    $output = tempnam(sys_get_temp_dir(), 'tugas');
    file_put_contents($output, str_repeat('x', 6000).'Koneksi database putus');
    $task = tugasTerjadwal()->sendOutputTo($output);
    $task->exitCode = 1;

    event(new ScheduledTaskFinished($task, 1.0));

    $log = LogTugasTerjadwal::sole();
    expect($log->status)->toBe(StatusTugasTerjadwal::Gagal)
        ->and($log->exit_code)->toBe(1)
        ->and(strlen($log->output))->toBe(5000)
        ->and($log->output)->toEndWith('Koneksi database putus');
});

test('tugas yang melempar exception dicatat gagal dengan pesan exception', function () {
    Notification::fake();

    event(new ScheduledTaskFailed(tugasTerjadwal(), new RuntimeException('Router tidak merespons')));

    $log = LogTugasTerjadwal::sole();
    expect($log->status)->toBe(StatusTugasTerjadwal::Gagal)
        ->and($log->output)->toBe('Router tidak merespons');
});

test('tugas yang dilewati karena overlap dicatat dilewati tanpa durasi', function () {
    $task = tugasTerjadwal();
    $task->skippedBecauseOverlapping = true;

    event(new ScheduledTaskFinished($task, 0.01));

    $log = LogTugasTerjadwal::sole();
    expect($log->status)->toBe(StatusTugasTerjadwal::Dilewati)
        ->and($log->durasi_detik)->toBeNull();
});

test('tugas frekuensi tinggi hanya dicatat saat gagal', function (string $jadwal) {
    Notification::fake();
    $task = app(Schedule::class)->command('mikrotik:ping')->{$jadwal}();

    $task->exitCode = 0;
    event(new ScheduledTaskFinished($task, 0.3));
    expect(LogTugasTerjadwal::count())->toBe(0);

    $task->exitCode = 1;
    event(new ScheduledTaskFinished($task, 0.3));
    expect(LogTugasTerjadwal::sole()->status)->toBe(StatusTugasTerjadwal::Gagal);
})->with([
    'tiap 10 detik' => 'everyTenSeconds',
    'tiap menit' => 'everyMinute',
]);

test('tugas runInBackground dicatat dari event background, bukan saat proses dilepas', function () {
    $task = tugasTerjadwal('mikrotik:provisi-router --async')->runInBackground();

    event(new ScheduledTaskFinished($task, 0.05));
    expect(LogTugasTerjadwal::count())->toBe(0);

    $task->exitCode = 0;
    event(new ScheduledBackgroundTaskFinished($task));

    $log = LogTugasTerjadwal::sole();
    expect($log->perintah)->toBe('mikrotik:provisi-router --async')
        ->and($log->status)->toBe(StatusTugasTerjadwal::Berhasil)
        ->and($log->durasi_detik)->toBeNull();
});

test('kegagalan mengirim satu alert ke super_admin dan noc per 30 menit', function () {
    Notification::fake();
    $superAdmin = userDenganPeran('super_admin');
    $noc = userDenganPeran('noc');
    $admin = userDenganPeran('admin');
    $task = tugasTerjadwal();
    $task->exitCode = 1;

    event(new ScheduledTaskFinished($task, 1.0));
    event(new ScheduledTaskFinished($task, 1.0));

    Notification::assertSentToTimes($superAdmin, TugasTerjadwalGagalNotification::class, 1);
    Notification::assertSentToTimes($noc, TugasTerjadwalGagalNotification::class, 1);
    Notification::assertNotSentTo($admin, TugasTerjadwalGagalNotification::class);

    $this->travel(31)->minutes();
    event(new ScheduledTaskFinished($task, 1.0));

    Notification::assertSentToTimes($noc, TugasTerjadwalGagalNotification::class, 2);
});

test('kesalahan saat mencatat log dilaporkan tanpa menghentikan penjadwal', function () {
    Exceptions::fake();
    Schema::drop('log_tugas_terjadwal');
    $task = tugasTerjadwal();
    $task->exitCode = 0;

    event(new ScheduledTaskFinished($task, 1.0));

    Exceptions::assertReported(QueryException::class);
});

test('log lebih dari 30 hari dihapus oleh model:prune', function () {
    $lama = LogTugasTerjadwal::create(['perintah' => 'invoice:generate', 'status' => StatusTugasTerjadwal::Berhasil, 'selesai_at' => now()->subDays(31)]);
    $baru = LogTugasTerjadwal::create(['perintah' => 'invoice:generate', 'status' => StatusTugasTerjadwal::Berhasil, 'selesai_at' => now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => LogTugasTerjadwal::class])->assertSuccessful();

    $this->assertModelMissing($lama);
    $this->assertModelExists($baru);
});

test('noc dapat membuka halaman log dan memfilter berdasarkan status', function () {
    LogTugasTerjadwal::create(['perintah' => 'invoice:generate', 'status' => StatusTugasTerjadwal::Berhasil, 'selesai_at' => now()]);
    LogTugasTerjadwal::create(['perintah' => 'layanan:cek-isolir', 'status' => StatusTugasTerjadwal::Gagal, 'output' => 'Timeout RouterOS', 'selesai_at' => now()]);

    $this->actingAs(userDenganPeran('noc'))
        ->get(route('tugas-terjadwal.index'))
        ->assertOk();

    Livewire::actingAs(userDenganPeran('noc'))
        ->test(Index::class)
        ->set('status', 'gagal')
        ->assertViewHas('logs', fn ($logs) => $logs->pluck('perintah')->all() === ['layanan:cek-isolir']);
});

test('peran tanpa izin tidak dapat membuka halaman log', function () {
    $this->actingAs(userDenganPeran('teknisi'))
        ->get(route('tugas-terjadwal.index'))
        ->assertForbidden();
});
