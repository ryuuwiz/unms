<?php

use App\Enums\JenisTagihanPertama;
use App\Enums\StatusLayanan;
use App\Enums\Wa\StatusAntrianWa;
use App\Events\InvoiceTerbitEvent;
use App\Listeners\KirimNotifikasiInvoiceTerbitListener;
use App\Models\AntrianWaBlast;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\User;
use App\Models\WaTemplate;
use App\Notifications\InvoiceTerbitNotification;
use App\Services\Billing\BillingService;
use App\Services\Whatsapp\WhatsappService;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(WaTemplateSeeder::class);

    $this->pelanggan = Pelanggan::factory()->create(['no_hp' => '081234567890', 'email' => 'pelanggan@example.test']);
    $paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => ProfilBandwidth::factory()->create()->id,
        'harga' => 300000,
    ]);
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'status' => StatusLayanan::Proses,
        'tanggal_mulai' => now()->toDateString(),
        'tanggal_expired' => now()->addMonth()->toDateString(),
    ]);
    $this->billing = app(BillingService::class);
});

function jalankanListenerInvoiceTerbit(Invoice $invoice): void
{
    app(KirimNotifikasiInvoiceTerbitListener::class)->handle(new InvoiceTerbitEvent($invoice));
}

test('ketiga jalur pembuatan invoice memancarkan InvoiceTerbitEvent, seeding langsung tidak', function () {
    Event::fake([InvoiceTerbitEvent::class]);

    $this->billing->generateManualInvoice($this->layanan, 50000, 'Biaya instalasi');
    $this->billing->generateFirstInvoice($this->layanan, JenisTagihanPertama::SatuBulanFull);
    $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-11');

    Event::assertDispatchedTimes(InvoiceTerbitEvent::class, 3);

    Invoice::factory()->create(['pelanggan_id' => $this->pelanggan->id]);
    Event::assertDispatchedTimes(InvoiceTerbitEvent::class, 3);
});

test('listener mengantrikan WA bertemplate invoice_terbit dengan tautan bayar dan mengirim email', function () {
    Queue::fake();
    Notification::fake();
    $invoice = Invoice::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'layanan_pelanggan_id' => $this->layanan->id]);

    jalankanListenerInvoiceTerbit($invoice);

    $antrian = AntrianWaBlast::where('jenis', 'invoice_terbit')->firstOrFail();
    expect($antrian->referensi_id)->toBe($invoice->id)
        ->and($antrian->pesan)->toContain('Tagihan baru layanan internet Anda telah terbit')
        ->and($antrian->pesan)->toContain($invoice->no_invoice)
        ->and($antrian->pesan)->toContain('/tagihan/'.$invoice->id)
        ->and($antrian->pesan)->not->toContain('{');

    Notification::assertSentTo($this->pelanggan, InvoiceTerbitNotification::class);
});

test('email invoice terbit dirender tanpa placeholder yang hilang', function () {
    $invoice = Invoice::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'layanan_pelanggan_id' => $this->layanan->id]);
    $whatsapp = app(WhatsappService::class);
    $params = $whatsapp->mergeCompanyParams($whatsapp->buildInvoiceParams($invoice));

    $mail = (new InvoiceTerbitNotification($invoice, $params))->toMail($this->pelanggan);

    expect($mail->subject)->toContain($invoice->no_invoice)
        ->and($mail->actionUrl)->toBe($params['link_pembayaran']);
});

test('pengiriman ditunda ke 08:30 WIB di luar jendela siang dan langsung di dalamnya', function () {
    $invoice = Invoice::factory()->create(['pelanggan_id' => $this->pelanggan->id]);
    $event = new InvoiceTerbitEvent($invoice);
    $listener = app(KirimNotifikasiInvoiceTerbitListener::class);

    Carbon::setTestNow(Carbon::create(2026, 9, 24, 10, 0, 0, 'Asia/Jakarta'));
    expect($listener->withDelay($event))->toBe(0);

    Carbon::setTestNow(Carbon::create(2026, 9, 24, 1, 0, 0, 'Asia/Jakarta'));
    expect($listener->withDelay($event)->format('Y-m-d H:i'))->toBe('2026-09-24 08:30');

    Carbon::setTestNow(Carbon::create(2026, 9, 24, 21, 0, 0, 'Asia/Jakarta'));
    expect($listener->withDelay($event)->format('Y-m-d H:i'))->toBe('2026-09-25 08:30');

    Carbon::setTestNow();
});

test('invoice buatan admin (tagihan pertama, manual) langsung dikirim walau malam hari', function () {
    $invoice = Invoice::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'dibuat_oleh' => User::factory()->create()->id]);

    Carbon::setTestNow(Carbon::create(2026, 9, 24, 21, 0, 0, 'Asia/Jakarta'));
    expect(app(KirimNotifikasiInvoiceTerbitListener::class)->withDelay(new InvoiceTerbitEvent($invoice)))->toBe(0);

    Carbon::setTestNow();
});

test('pelanggan tanpa no HP dilewati, no HP tidak valid tercatat Gagal, email tetap terkirim', function () {
    Queue::fake();
    Notification::fake();

    $this->pelanggan->update(['no_hp' => '']);
    $invoice = Invoice::factory()->create(['pelanggan_id' => $this->pelanggan->id]);
    jalankanListenerInvoiceTerbit($invoice);
    expect(AntrianWaBlast::count())->toBe(0);
    Notification::assertSentTo($this->pelanggan, InvoiceTerbitNotification::class);

    $this->pelanggan->update(['no_hp' => '123']);
    jalankanListenerInvoiceTerbit($invoice->fresh());
    expect(AntrianWaBlast::firstOrFail()->status)->toBe(StatusAntrianWa::Gagal);
});

test('kegagalan kirim WA tidak menggagalkan pembuatan invoice', function () {
    $mock = Mockery::mock(WhatsappService::class)->makePartial();
    $mock->shouldReceive('antrikanPesan')->andThrow(new RuntimeException('gateway mati'));
    $this->app->instance(WhatsappService::class, $mock);
    Notification::fake();

    $invoice = $this->billing->generateManualInvoice($this->layanan, 50000, 'Denda');

    expect($invoice->exists)->toBeTrue()
        ->and(Invoice::whereKey($invoice->id)->exists())->toBeTrue();
});

test('kirim ulang pada hari yang sama tidak menggandakan antrean WA', function () {
    Queue::fake();
    Notification::fake();
    $invoice = Invoice::factory()->create(['pelanggan_id' => $this->pelanggan->id]);

    jalankanListenerInvoiceTerbit($invoice);
    jalankanListenerInvoiceTerbit($invoice);

    expect(AntrianWaBlast::where('jenis', 'invoice_terbit')->count())->toBe(1);
});

test('migrasi menambahkan template invoice_terbit bila hilang dan tidak menimpa hasil edit admin', function () {
    $migrasi = require database_path('migrations/2026_09_24_055039_seed_wa_template_invoice_terbit.php');

    WaTemplate::where('kode', 'invoice_terbit')->delete();
    $migrasi->up();
    expect(WaTemplate::where('kode', 'invoice_terbit')->count())->toBe(1);

    WaTemplate::where('kode', 'invoice_terbit')->update(['konten' => 'Edit admin {nama_pelanggan}']);
    $migrasi->up();
    expect(WaTemplate::where('kode', 'invoice_terbit')->value('konten'))->toBe('Edit admin {nama_pelanggan}');
});
