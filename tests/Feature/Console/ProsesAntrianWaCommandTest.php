<?php

use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Models\AntrianWaBlast;
use App\Models\Sysblas;
use Database\Seeders\SysblasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SysblasSeeder::class);
    $this->sysblas = Sysblas::first();
});

test('wa:proses-antrian men-dispatch ulang antrean menunggu yang sudah jatuh tempo ke KirimWaBlastJob, tanpa mengirim langsung', function () {
    Queue::fake([KirimWaBlastJob::class]);

    $antrian = AntrianWaBlast::create([
        'sysblas_id' => $this->sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Pesan tertunda',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Menunggu,
        'dijadwalkan_pada' => Carbon::now()->subMinute(),
    ]);

    $this->artisan('wa:proses-antrian')->assertSuccessful();

    Queue::assertPushed(KirimWaBlastJob::class, function ($job) use ($antrian) {
        return $job->antrian->id === $antrian->id;
    });

    // Status tidak berubah di sisi command — hanya KirimWaBlastJob (yang di-fake, tidak
    // benar-benar jalan) yang boleh memperbarui status/mengirim pesan.
    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Menunggu);
});

test('wa:proses-antrian mengabaikan antrean yang dijadwalkan di masa depan', function () {
    Queue::fake([KirimWaBlastJob::class]);

    AntrianWaBlast::create([
        'sysblas_id' => $this->sysblas->id,
        'no_hp_tujuan' => '6281234567891',
        'pesan' => 'Pesan terjadwal nanti',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Menunggu,
        'dijadwalkan_pada' => Carbon::now()->addHour(),
    ]);

    $this->artisan('wa:proses-antrian')->assertSuccessful();

    Queue::assertNotPushed(KirimWaBlastJob::class);
});

test('wa:proses-antrian mengabaikan antrean yang bukan berstatus menunggu', function () {
    Queue::fake([KirimWaBlastJob::class]);

    AntrianWaBlast::create([
        'sysblas_id' => $this->sysblas->id,
        'no_hp_tujuan' => '6281234567892',
        'pesan' => 'Pesan sudah terkirim',
        'jenis' => 'tagihan',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Terkirim,
        'dijadwalkan_pada' => Carbon::now()->subMinute(),
    ]);

    $this->artisan('wa:proses-antrian')->assertSuccessful();

    Queue::assertNotPushed(KirimWaBlastJob::class);
});

test('wa:proses-antrian menghormati opsi --limit', function () {
    Queue::fake([KirimWaBlastJob::class]);

    for ($i = 0; $i < 3; $i++) {
        AntrianWaBlast::create([
            'sysblas_id' => $this->sysblas->id,
            'no_hp_tujuan' => '62812345678'.$i,
            'pesan' => "Pesan antrean {$i}",
            'jenis' => 'tagihan',
            'tanggal_kirim' => Carbon::today(),
            'status' => StatusAntrianWa::Menunggu,
            'dijadwalkan_pada' => Carbon::now()->subMinute(),
        ]);
    }

    $this->artisan('wa:proses-antrian', ['--limit' => 2])->assertSuccessful();

    Queue::assertPushed(KirimWaBlastJob::class, 2);
});
