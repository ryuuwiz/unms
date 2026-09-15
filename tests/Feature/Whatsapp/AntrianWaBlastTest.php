<?php

use App\Enums\StatusPelanggan;
use App\Enums\Wa\StatusAntrianWa;
use App\Jobs\Wa\KirimWaBlastJob;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Services\Whatsapp\WhatsappService;
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

    /** @var WhatsappService $service */
    $service = app(WhatsappService::class);
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

    /** @var WhatsappService $service */
    $service = app(WhatsappService::class);
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

    /** @var WhatsappService $service */
    $service = app(WhatsappService::class);

    $antrian = $service->antrikanPesanKustom(
        noHp: 'invalid_phone_123',
        pesan: 'Pesan pengujian nomor salah'
    );

    expect($antrian)->not->toBeNull()
        ->and($antrian->status)->toBe(StatusAntrianWa::Gagal)
        ->and($antrian->pesan_error)->toContain('tidak valid');

    Queue::assertNotPushed(KirimWaBlastJob::class);
});

test('buildInvoiceParams menyertakan kode_bayar 5 digit, status_internet, dan tautan checkout bertanda tangan', function () {
    $pelanggan = Pelanggan::factory()->create(['status' => StatusPelanggan::Aktif]);
    $invoice = Invoice::factory()->create(['pelanggan_id' => $pelanggan->id]);

    /** @var WhatsappService $service */
    $service = app(WhatsappService::class);
    $params = $service->buildInvoiceParams($invoice);

    expect($params['kode_bayar'])->toMatch('/^\d{5}$/')
        ->and($params['status_internet'])->toBe('Aktif')
        ->and($params['link_pembayaran'])->toContain(route('portal.invoice.show', $invoice->id))
        ->and($params['link_pembayaran'])->toContain('signature=');
});

test('template pengingat tagihan tidak menyisakan placeholder setelah dirender', function () {
    Queue::fake();

    $pelanggan = Pelanggan::factory()->create();
    $invoice = Invoice::factory()->create(['pelanggan_id' => $pelanggan->id]);

    /** @var WhatsappService $service */
    $service = app(WhatsappService::class);
    $params = $service->buildInvoiceParams($invoice);

    // Lewat antrikanPesan() (bukan render() langsung) agar parameter perusahaan
    // (nama_brand, whatsapp_perusahaan, dst.) ikut digabungkan seperti alur produksi
    // sesungguhnya -- lihat WhatsappService::mergeCompanyParams().
    foreach (['pengingat_tagihan_h3', 'pengingat_tagihan_h1', 'pengingat_tagihan_h0', 'pengingat_tagihan_tunggakan'] as $kode) {
        $antrian = $service->antrikanPesan(
            noHp: $pelanggan->no_hp,
            kodeTemplate: $kode,
            params: $params,
        );

        expect($antrian->pesan)->not->toContain('{');
    }
});
