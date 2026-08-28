<?php

use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Services\Wablas\WablasService;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(WaTemplateSeeder::class);
});

test('antrikanPesan berhasil membuat record outbox dan mendispatch job queue', function () {
    Queue::fake();

    $pelanggan = Pelanggan::factory()->create([
        'no_hp' => '081298765432',
    ]);

    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'jumlah' => 250000,
        'tanggal_jatuh_tempo' => Carbon::now()->addDays(3),
    ]);

    /** @var WablasService $service */
    $service = app(WablasService::class);
    $params = $service->buildInvoiceParams($invoice);

    $antrian = $service->antrikanPesan(
        noHp: $pelanggan->no_hp,
        kodeTemplate: 'pengingat_tagihan_h3',
        params: $params,
        referensi: $invoice,
        jenis: 'pengingat_tagihan_h3',
        tanggalTarget: Carbon::today()
    );

    expect($antrian)->not->toBeNull()
        ->and($antrian->no_hp_tujuan)->toBe('6281298765432')
        ->and($antrian->status)->toBe(StatusAntrianWa::Menunggu)
        ->and($antrian->pesan)->toContain('250.000');

    Queue::assertPushed(KirimWaBlastJob::class, function ($job) use ($antrian) {
        return $job->antrian->id === $antrian->id;
    });
});

test('antrikanPesan menerapkan penjaminan idempotensi harian', function () {
    Queue::fake();

    $pelanggan = Pelanggan::factory()->create(['no_hp' => '081298765432']);
    $invoice = Invoice::factory()->create(['pelanggan_id' => $pelanggan->id]);

    /** @var WablasService $service */
    $service = app(WablasService::class);
    $params = $service->buildInvoiceParams($invoice);

    $antrian1 = $service->antrikanPesan(
        noHp: $pelanggan->no_hp,
        kodeTemplate: 'pengingat_tagihan_h3',
        params: $params,
        referensi: $invoice,
        jenis: 'pengingat_tagihan_h3',
        tanggalTarget: Carbon::today()
    );

    // Percobaan kedua pada hari yang sama
    $antrian2 = $service->antrikanPesan(
        noHp: $pelanggan->no_hp,
        kodeTemplate: 'pengingat_tagihan_h3',
        params: $params,
        referensi: $invoice,
        jenis: 'pengingat_tagihan_h3',
        tanggalTarget: Carbon::today()
    );

    expect($antrian1->id)->toBe($antrian2->id)
        ->and(AntrianWaBlast::count())->toBe(1);
});

test('antrikanPesan aman dan menandai status gagal saat nomor hp tidak valid', function () {
    Queue::fake();

    /** @var WablasService $service */
    $service = app(WablasService::class);

    $antrian = $service->antrikanPesanKustom(
        noHp: 'invalid_phone_123',
        pesan: 'Pesan pengujian nomor salah'
    );

    expect($antrian)->not->toBeNull()
        ->and($antrian->status)->toBe(StatusAntrianWa::Gagal)
        ->and($antrian->pesan_error)->toContain('tidak valid');

    Queue::assertNotPushed(KirimWaBlastJob::class);
});
