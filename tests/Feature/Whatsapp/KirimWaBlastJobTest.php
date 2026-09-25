<?php

use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Models\AntrianWaBlast;
use App\Models\Sysblas;
use App\Models\User;
use App\Notifications\GatewayWaBermasalahNotification;
use App\Services\Whatsapp\WhatsappClient;
use Database\Seeders\SysblasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SysblasSeeder::class);
    $this->sysblas = Sysblas::first();
    Role::findOrCreate('super_admin');
    $this->admin = User::factory()->create()->assignRole(Role::findOrCreate('admin'));
    Notification::fake();
});

function antrianWa(Sysblas $sysblas): AntrianWaBlast
{
    return AntrianWaBlast::create([
        'sysblas_id' => $sysblas->id,
        'no_hp_tujuan' => '6281234567890',
        'pesan' => 'Tagihan terbit',
        'jenis' => 'invoice_terbit',
        'tanggal_kirim' => Carbon::today(),
        'status' => StatusAntrianWa::Menunggu,
        'dijadwalkan_pada' => Carbon::now(),
    ]);
}

function kirimWa(AntrianWaBlast $antrian): void
{
    // Jeda Antar-Pesan dari percobaan sebelumnya tidak relevan di sini.
    Cache::forget("sysblas-next-send-slot-{$antrian->sysblas_id}");
    (new KirimWaBlastJob($antrian))->handle(app(WhatsappClient::class));
}

test('batas percobaan berbasis waktu dan job unik per antrean, sehingga penyapu tidak menggandakannya', function () {
    Queue::fake();
    $antrian = antrianWa($this->sysblas);

    $job = new KirimWaBlastJob($antrian);
    expect($job->retryUntil()->getTimestamp())->toBeGreaterThanOrEqual(now()->addMinutes(119)->getTimestamp())
        ->and(property_exists($job, 'tries'))->toBeFalse();

    KirimWaBlastJob::dispatch($antrian);
    $this->artisan('wa:proses-antrian')->assertSuccessful();

    Queue::assertPushed(KirimWaBlastJob::class, 1);
});

test('galat sementara (HTTP 5xx) dicoba ulang dan lonceng admin maksimal sekali per jam', function () {
    Http::fake(['*/send/message' => Http::response(['message' => 'bad gateway'], 502)]);
    $antrian = antrianWa($this->sysblas);

    expect(fn () => kirimWa($antrian))->toThrow(RuntimeException::class);
    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Menunggu)
        ->and($antrian->fresh()->pesan_error)->toBe('bad gateway');

    expect(fn () => kirimWa(antrianWa($this->sysblas)))->toThrow(RuntimeException::class);

    Notification::assertSentToTimes($this->admin, GatewayWaBermasalahNotification::class, 1);
});

test('gateway menolak autentikasi: pesan gagal permanen dan admin diberi tahu', function () {
    Http::fake(['*/send/message' => Http::response([], 401)]);
    $antrian = antrianWa($this->sysblas);

    kirimWa($antrian);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Gagal)
        ->and($antrian->fresh()->pesan_error)->toBe('HTTP 401');
    Notification::assertSentTo($this->admin, GatewayWaBermasalahNotification::class);
});

test('penolakan per nomor gagal permanen tanpa lonceng dan tercatat di log whatsapp', function () {
    Http::fake(['*/send/message' => Http::response(['message' => 'nomor tidak terdaftar'], 400)]);
    $log = Mockery::mock();
    $log->shouldReceive('error')->once()->with('Pesan WA gagal terkirim', Mockery::on(fn (array $konteks) => $konteks['jenis'] === 'invoice_terbit' && $konteks['error'] === 'nomor tidak terdaftar'));
    Log::shouldReceive('channel')->with('whatsapp')->andReturn($log);
    $antrian = antrianWa($this->sysblas);

    kirimWa($antrian);

    expect($antrian->fresh()->status)->toBe(StatusAntrianWa::Gagal);
    Notification::assertNothingSent();
});
