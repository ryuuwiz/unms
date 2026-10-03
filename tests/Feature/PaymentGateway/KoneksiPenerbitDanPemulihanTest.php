<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Events\InvoicePaidEvent;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\Router;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    fakeXenditSession();
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([InvoicePaidEvent::class]);

    // Baris sandbox dibuat lebih dulu (id terkecil) dan tidak aktif -- persis kondisi
    // production yang membuat token koneksi yang salah terpakai.
    $this->sandbox = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit-sandbox',
        'nama' => 'Xendit Sandbox',
        'credentials' => ['secret_key' => 'xnd_development_x', 'callback_token' => 'token_sandbox'],
        'is_default' => false,
        'is_active' => false,
        'sandbox_mode' => true,
    ]);
    $this->live = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Live',
        'credentials' => ['secret_key' => 'xnd_production_x', 'callback_token' => 'token_live'],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => false,
    ]);

    $this->manager = app(PaymentGatewayManager::class);

    $pelanggan = Pelanggan::factory()->create(['no_reg' => 'BF0210202601']);
    $paket = PaketLayanan::factory()->create(['harga' => 200000, 'masa_aktif_nilai' => 1, 'masa_aktif_satuan' => 'bulan']);
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'router_id' => Router::factory()->create()->id,
        'paket_layanan_id' => $paket->id,
        'tanggal_expired' => now()->toDateString(),
        'status' => StatusLayanan::Aktif,
    ]);
    $this->invoice = Invoice::create([
        'pelanggan_id' => $pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => now()->format('Y-m'),
        'jumlah' => 200000,
        'jumlah_setelah_promo' => 200000,
        'tanggal_terbit' => now()->toDateString(),
        'tanggal_jatuh_tempo' => now()->addDays(7)->toDateString(),
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

/**
 * @param  array<string, mixed>  $atribut
 */
function buatTransaksi(Invoice $invoice, ?PengaturanGateway $koneksi, array $atribut = []): TransaksiPaymentGateway
{
    return TransaksiPaymentGateway::create(array_merge([
        'invoice_id' => $invoice->id,
        'gateway' => 'xendit',
        'pengaturan_gateway_id' => $koneksi?->id,
        'external_id' => $invoice->no_invoice.'-'.uniqid(),
        'xendit_reference_id' => 'inv_'.uniqid(),
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => 200000,
        'fee_gateway' => 0,
        'status' => StatusTransaksiGateway::Pending,
        'expired_at' => now()->addDay(),
    ], $atribut));
}

/**
 * Pasang driver Xendit palsu yang melaporkan status tertentu dari checkStatus().
 */
function pasangDriverStatus(PaymentGatewayManager $manager, string $status): void
{
    $manager->registerDriver('xendit', new class($status) extends XenditDriver
    {
        public function __construct(private string $status) {}

        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            $amount = $target instanceof TransaksiPaymentGateway ? (float) $target->total_tagihan : 0.0;

            return ['id' => 'xnd_'.uniqid(), 'status' => $this->status, 'amount' => $amount, 'paid_amount' => $amount];
        }
    });
    app()->instance(PaymentGatewayManager::class, $manager);
}

function callbackPaid(TransaksiPaymentGateway $transaksi): array
{
    return [
        'id' => $transaksi->xendit_reference_id,
        'external_id' => $transaksi->external_id,
        'status' => 'PAID',
        'amount' => 200000,
        'paid_amount' => 200000,
        'paid_at' => now()->toIso8601String(),
    ];
}

test('koneksi untuk provider mengutamakan yang aktif dan default, bukan baris pertama', function () {
    expect(PengaturanGateway::getSettingForProvider('xendit')->is($this->live))->toBeTrue();
});

test('transaksi baru mencatat koneksi yang menerbitkannya', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    expect($transaksi->pengaturan_gateway_id)->toBe($this->live->id);
});

test('callback diverifikasi dengan token koneksi penerbit transaksi meski koneksi default berbeda', function () {
    $transaksi = buatTransaksi($this->invoice, $this->sandbox);

    $this->postJson('/webhook/payment/xendit', callbackPaid($transaksi), ['x-callback-token' => 'token_sandbox'])
        ->assertOk();

    expect($this->invoice->fresh()->isLunas())->toBeTrue();
});

test('callback dengan token koneksi lain ditolak 401 untuk transaksi milik koneksi penerbit', function () {
    $transaksi = buatTransaksi($this->invoice, $this->sandbox);

    $this->postJson('/webhook/payment/xendit', callbackPaid($transaksi), ['x-callback-token' => 'token_live'])
        ->assertUnauthorized();

    expect($this->invoice->fresh()->isLunas())->toBeFalse();
});

test('callback untuk transaksi tanpa koneksi tercatat diverifikasi dengan koneksi aktif dan default', function () {
    $transaksi = buatTransaksi($this->invoice, null);

    $this->postJson('/webhook/payment/xendit', callbackPaid($transaksi), ['x-callback-token' => 'token_sandbox'])
        ->assertUnauthorized();
    $this->postJson('/webhook/payment/xendit', callbackPaid($transaksi), ['x-callback-token' => 'token_live'])
        ->assertOk();

    expect($this->invoice->fresh()->isLunas())->toBeTrue();
});

test('di production, transaksi koneksi sandbox tidak melunasi invoice', function () {
    $transaksi = buatTransaksi($this->invoice, $this->sandbox);
    app()->detectEnvironment(fn () => 'production');

    $hasil = $this->manager->prosesPelunasan($this->invoice, callbackPaid($transaksi), $transaksi);

    expect($hasil)->toBeFalse()
        ->and($this->invoice->fresh()->isLunas())->toBeFalse();
});

test('cek kedaluwarsa tidak menandai Expired selama gateway masih PENDING', function () {
    $transaksi = buatTransaksi($this->invoice, $this->live, ['expired_at' => now()->subHour()]);
    pasangDriverStatus($this->manager, 'PENDING');

    $this->artisan('xendit:cek-va-expired')->assertSuccessful();

    expect($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Pending);
});

test('cek kedaluwarsa menandai Expired hanya saat gateway menyatakan EXPIRED', function () {
    $transaksi = buatTransaksi($this->invoice, $this->live, ['expired_at' => now()->subHour()]);
    pasangDriverStatus($this->manager, 'EXPIRED');

    $this->artisan('xendit:cek-va-expired')->assertSuccessful();

    expect($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Expired);
});

test('cek kedaluwarsa melunasi transaksi lewat waktu yang ternyata sudah PAID di gateway', function () {
    buatTransaksi($this->invoice, $this->live, ['expired_at' => now()->subHour()]);
    pasangDriverStatus($this->manager, 'PAID');

    $this->artisan('xendit:cek-va-expired')->assertSuccessful();

    expect($this->invoice->fresh()->isLunas())->toBeTrue();
});

test('transaksi lama yang kedaluwarsa tidak menghapus tautan pembayaran transaksi terbaru', function () {
    $lama = buatTransaksi($this->invoice, $this->live, ['expired_at' => now()->subHour()]);
    buatTransaksi($this->invoice, $this->live);
    $this->invoice->update(['payment_gateway_url' => 'https://checkout.xendit.co/web/baru']);
    pasangDriverStatus($this->manager, 'EXPIRED');

    $this->manager->sinkronkanTransaksi($lama);

    expect($lama->fresh()->status)->toBe(StatusTransaksiGateway::Expired)
        ->and($this->invoice->fresh()->payment_gateway_url)->toBe('https://checkout.xendit.co/web/baru');
});

test('pembayaran:pulihkan melunasi transaksi Expired yang ternyata PAID di gateway', function () {
    $transaksi = buatTransaksi($this->invoice, $this->live, ['status' => StatusTransaksiGateway::Expired]);
    pasangDriverStatus($this->manager, 'PAID');

    $this->artisan('pembayaran:pulihkan')->assertSuccessful();

    expect($this->invoice->fresh()->isLunas())->toBeTrue()
        ->and($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Paid);
});

test('pembayaran:pulihkan --dry-run tidak mengubah data', function () {
    $transaksi = buatTransaksi($this->invoice, $this->live, ['status' => StatusTransaksiGateway::Expired]);
    pasangDriverStatus($this->manager, 'PAID');

    $this->artisan('pembayaran:pulihkan', ['--dry-run' => true])->assertSuccessful();

    expect($this->invoice->fresh()->isLunas())->toBeFalse()
        ->and($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Expired);
});

test('callback EXPIRED untuk transaksi lama tidak menghapus tautan pembayaran transaksi terbaru', function () {
    $lama = buatTransaksi($this->invoice, $this->live);
    buatTransaksi($this->invoice, $this->live);
    $this->invoice->update(['payment_gateway_url' => 'https://checkout.xendit.co/web/baru']);

    $this->postJson('/webhook/payment/xendit', [
        'id' => $lama->xendit_reference_id,
        'external_id' => $lama->external_id,
        'status' => 'EXPIRED',
        'amount' => 200000,
    ], ['x-callback-token' => 'token_live'])->assertOk();

    expect($lama->fresh()->status)->toBe(StatusTransaksiGateway::Expired)
        ->and($this->invoice->fresh()->payment_gateway_url)->toBe('https://checkout.xendit.co/web/baru');
});

test('invoice yang tidak ditemukan di gateway tidak membuat transaksi Kedaluwarsa', function () {
    $transaksi = buatTransaksi($this->invoice, $this->live, ['expired_at' => now()->subHour()]);
    $this->manager->registerDriver('xendit', new class extends XenditDriver
    {
        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            return ['error' => 'Invoice Xendit tidak ditemukan berdasarkan external_id.', 'error_code' => 'external_id_not_found'];
        }
    });
    $this->app->instance(PaymentGatewayManager::class, $this->manager);

    $this->artisan('xendit:cek-va-expired')->assertSuccessful();

    expect($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Pending);
});

test('di production, callback yang diverifikasi dengan koneksi default sandbox tidak melunasi invoice', function () {
    $this->sandbox->update(['is_active' => true]);
    $this->sandbox->setAsDefault();
    buatTransaksi($this->invoice, $this->live);
    app()->detectEnvironment(fn () => 'production');

    $hasil = $this->manager->prosesPelunasan($this->invoice, ['paid_amount' => 200000, 'id' => 'evt_tak_dikenal']);

    expect($hasil)->toBeFalse()
        ->and($this->invoice->fresh()->isLunas())->toBeFalse();
});

test('pembayaran:pulihkan menghitung satu invoice sekali walau punya beberapa transaksi', function () {
    buatTransaksi($this->invoice, $this->live, ['status' => StatusTransaksiGateway::Expired]);
    buatTransaksi($this->invoice, $this->live, ['status' => StatusTransaksiGateway::Expired]);
    pasangDriverStatus($this->manager, 'PAID');

    $this->artisan('pembayaran:pulihkan')
        ->expectsOutputToContain('Selesai. 1 invoice dilunasi.')
        ->assertSuccessful();
});
