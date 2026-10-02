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
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([InvoicePaidEvent::class]);

    $this->koneksi = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Produksi',
        'credentials' => ['secret_key' => 'xnd_production_a', 'callback_token' => 'token-a'],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => false,
    ]);

    $paket = PaketLayanan::factory()->create(['harga' => 150000, 'masa_aktif_nilai' => 1, 'masa_aktif_satuan' => 'bulan']);
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => Pelanggan::factory()->create()->id,
        'paket_layanan_id' => $paket->id,
        'tanggal_expired' => now()->subDays(10)->toDateString(),
        'status' => StatusLayanan::Suspend,
    ]);

    // Pembayaran PAID per koneksi yang dikembalikan driver palsu: [pengaturan_gateway_id => list<array>].
    $this->pembayaranXendit = [];
    $this->invoiceDikedaluwarsakan = [];
    $this->expireGagal = false;
    $this->manager = app(PaymentGatewayManager::class);
    $test = $this;
    $this->manager->registerDriver('xendit', new class($test) extends XenditDriver
    {
        public function __construct(private $test) {}

        public function daftarPembayaranLunas(PengaturanGateway $setting, CarbonInterface $sejak, ?CarbonInterface $sampai = null): array
        {
            if (($this->test->pembayaranXendit[$setting->id] ?? null) === 'error') {
                throw new RuntimeException('HTTP 401 INVALID_API_KEY');
            }

            return array_map(fn (array $item) => $this->petakanPayload($item), $this->test->pembayaranXendit[$setting->id] ?? []);
        }

        public function kedaluwarsakanInvoice(TransaksiPaymentGateway $transaksi, PengaturanGateway $setting): void
        {
            if ($this->test->expireGagal) {
                throw new RuntimeException('HTTP 503 Xendit tidak tersedia');
            }

            $this->test->invoiceDikedaluwarsakan[] = $transaksi->xendit_reference_id;
        }
    });
    $this->app->instance(PaymentGatewayManager::class, $this->manager);
});

function invoiceSusulan(LayananPelanggan $layanan, StatusInvoice $status = StatusInvoice::MenungguPembayaran, array $atribut = []): Invoice
{
    return Invoice::create(array_merge([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => now()->format('Y-m'),
        'jumlah' => 150000,
        'jumlah_setelah_promo' => 150000,
        'tanggal_terbit' => now()->subDays(20)->toDateString(),
        'tanggal_jatuh_tempo' => now()->subDays(13)->toDateString(),
        'status' => $status,
    ], $atribut));
}

function transaksiSusulan(Invoice $invoice, PengaturanGateway $koneksi, StatusTransaksiGateway $status = StatusTransaksiGateway::Expired): TransaksiPaymentGateway
{
    return TransaksiPaymentGateway::create([
        'invoice_id' => $invoice->id,
        'pengaturan_gateway_id' => $koneksi->id,
        'gateway' => 'xendit',
        'external_id' => $invoice->no_invoice.'-1700000000',
        'xendit_reference_id' => 'inv_'.$invoice->id,
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => $invoice->jumlah_setelah_promo,
        'fee_gateway' => 0,
        'status' => $status,
    ]);
}

function bayarXendit(TransaksiPaymentGateway|string $target, float $nominal = 150000, string $paidAt = '2026-09-25T03:15:00.000Z'): array
{
    $externalId = $target instanceof TransaksiPaymentGateway ? $target->external_id : $target;

    return [
        'id' => 'inv_paid_'.md5($externalId),
        'external_id' => $externalId,
        'status' => 'PAID',
        'amount' => $nominal,
        'paid_amount' => $nominal,
        'paid_at' => $paidAt,
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BNI',
        'payment_id' => 'pay_'.md5($externalId),
        'currency' => 'IDR',
    ];
}

test('invoice Menunggu Pembayaran dan Kadaluarsa yang PAID di Xendit dilunasi dengan waktu bayar asli', function (StatusInvoice $status) {
    $invoice = invoiceSusulan($this->layanan, $status);
    $transaksi = transaksiSusulan($invoice, $this->koneksi);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($transaksi)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('DILUNASI');

    $invoice->refresh();
    expect($invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($invoice->tanggal_lunas->toDateString())->toBe('2026-09-25')
        ->and($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Paid)
        ->and(Pembayaran::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and($this->layanan->fresh()->tanggal_expired->greaterThan(now()))->toBeTrue();
    Event::assertDispatched(InvoicePaidEvent::class);
})->with([StatusInvoice::MenungguPembayaran, StatusInvoice::Kadaluarsa]);

test('pembayaran tanpa baris transaksi lokal dicocokkan lewat nomor invoice di external_id', function () {
    $invoice = invoiceSusulan($this->layanan);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($invoice->no_invoice.'-1699999999')];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas);
});

test('nominal yang tidak sama persis, external_id tak dikenal, dan pembayaran ganda dilaporkan tanpa mengubah data', function () {
    $nominalBeda = invoiceSusulan($this->layanan, atribut: ['periode_tagihan' => '2026-07']);
    $trxBeda = transaksiSusulan($nominalBeda, $this->koneksi);

    $sudahLunas = invoiceSusulan($this->layanan, StatusInvoice::Lunas, ['periode_tagihan' => '2026-08']);
    $trxLama = transaksiSusulan($sudahLunas, $this->koneksi);

    $this->pembayaranXendit[$this->koneksi->id] = [
        bayarXendit($trxBeda, 100000),
        bayarXendit('BFINV-TIDAKADA-1700000000'),
        bayarXendit($trxLama),
    ];

    $this->artisan('pembayaran:cek-lunas-xendit')
        ->assertSuccessful()
        ->expectsOutputToContain('Nominal tidak sama')
        ->expectsOutputToContain('tidak dikenal')
        ->expectsOutputToContain('Pembayaran ganda');

    expect($nominalBeda->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and(Pembayaran::count())->toBe(0);
    Event::assertNotDispatched(InvoicePaidEvent::class);
});

test('--dry-run tidak mengubah data dan menjalankan ulang tidak melunasi dua kali', function () {
    $invoice = invoiceSusulan($this->layanan);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit(transaksiSusulan($invoice, $this->koneksi))];

    $this->artisan('pembayaran:cek-lunas-xendit', ['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('AKAN DILUNASI');
    expect($invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran);

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful();
    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->doesntExpectOutputToContain('Pembayaran ganda');

    expect(Pembayaran::where('invoice_id', $invoice->id)->count())->toBe(1);
    Event::assertDispatchedTimes(InvoicePaidEvent::class, 1);
});

test('koneksi sandbox dilewati, koneksi nonaktif tetap diperiksa, dan satu koneksi gagal tidak menghentikan yang lain', function () {
    $sandbox = PengaturanGateway::create([
        'provider' => 'xendit', 'gateway' => 'xendit', 'nama' => 'Xendit Sandbox',
        'credentials' => ['secret_key' => 'xnd_development_x'], 'is_active' => true, 'sandbox_mode' => true,
    ]);
    $nonaktif = PengaturanGateway::create([
        'provider' => 'xendit', 'gateway' => 'xendit', 'nama' => 'Xendit Lama',
        'credentials' => ['secret_key' => 'xnd_production_lama'], 'is_active' => false, 'sandbox_mode' => false,
    ]);

    $dariSandbox = invoiceSusulan($this->layanan, atribut: ['periode_tagihan' => '2026-07']);
    $dariLama = invoiceSusulan($this->layanan, atribut: ['periode_tagihan' => '2026-08']);
    $this->pembayaranXendit[$sandbox->id] = [bayarXendit(transaksiSusulan($dariSandbox, $sandbox))];
    $this->pembayaranXendit[$nonaktif->id] = [bayarXendit(transaksiSusulan($dariLama, $nonaktif))];
    $this->pembayaranXendit[$this->koneksi->id] = 'error';

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('INVALID_API_KEY');

    expect($dariSandbox->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($dariLama->fresh()->status)->toBe(StatusInvoice::Lunas);
});

/**
 * Invoice September (Rp150.000) yang sudah digabung ke invoice Oktober (Rp300.000, tunggakan Rp150.000).
 *
 * @return array{0: Invoice, 1: TransaksiPaymentGateway, 2: Invoice}
 */
function invoiceDigabungKeOktober(LayananPelanggan $layanan, PengaturanGateway $koneksi, StatusInvoice $statusOktober = StatusInvoice::MenungguPembayaran): array
{
    $oktober = invoiceSusulan($layanan, $statusOktober, [
        'periode_tagihan' => '2026-10',
        'jumlah' => 150000,
        'jumlah_setelah_promo' => 300000,
        'jumlah_tunggakan' => 150000,
    ]);
    $september = invoiceSusulan($layanan, StatusInvoice::Digabung, ['periode_tagihan' => '2026-09', 'digabung_ke_invoice_id' => $oktober->id]);

    return [$september, transaksiSusulan($september, $koneksi), $oktober];
}

test('invoice Digabung yang dibayar dilunasi, dilepas dari penggabung, dan nominal serta link penggabung dikoreksi', function () {
    [$september, $trxSeptember, $oktober] = invoiceDigabungKeOktober($this->layanan, $this->koneksi);
    $trxOktober = transaksiSusulan($oktober, $this->koneksi, StatusTransaksiGateway::Pending);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($trxSeptember)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('DILUNASI');

    $september->refresh();
    $oktober->refresh();
    expect($september->status)->toBe(StatusInvoice::Lunas)
        ->and($september->digabung_ke_invoice_id)->toBeNull()
        ->and((float) $oktober->jumlah_setelah_promo)->toBe(150000.0)
        ->and((float) $oktober->jumlah_tunggakan)->toBe(0.0)
        ->and($oktober->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($trxOktober->fresh()->status)->toBe(StatusTransaksiGateway::Expired)
        ->and($this->invoiceDikedaluwarsakan)->toBe([$trxOktober->xendit_reference_id]);

    $linkBaru = $oktober->transaksiPaymentGatewayAktif();
    expect($linkBaru->id)->not->toBe($trxOktober->id)
        ->and($linkBaru->status)->toBe(StatusTransaksiGateway::Pending)
        ->and((float) $linkBaru->total_tagihan - (float) $linkBaru->fee_gateway)->toBe(150000.0);
});

test('invoice Digabung yang penggabungnya sudah Lunas dilaporkan sebagai pembayaran ganda', function () {
    [$september, $trxSeptember, $oktober] = invoiceDigabungKeOktober($this->layanan, $this->koneksi, StatusInvoice::Lunas);
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($trxSeptember)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('Pembayaran ganda');

    expect($september->fresh()->status)->toBe(StatusInvoice::Digabung)
        ->and((float) $oktober->fresh()->jumlah_setelah_promo)->toBe(300000.0);
});

test('gagal mematikan link penggabung di Xendit tidak membatalkan pelunasan dan dilaporkan', function () {
    [$september, $trxSeptember, $oktober] = invoiceDigabungKeOktober($this->layanan, $this->koneksi);
    transaksiSusulan($oktober, $this->koneksi, StatusTransaksiGateway::Pending);
    $this->expireGagal = true;
    $this->pembayaranXendit[$this->koneksi->id] = [bayarXendit($trxSeptember)];

    $this->artisan('pembayaran:cek-lunas-xendit')->assertSuccessful()->expectsOutputToContain('Xendit tidak tersedia');

    $oktober->refresh();
    expect($september->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and((float) $oktober->jumlah_setelah_promo)->toBe(150000.0)
        ->and($oktober->payment_gateway_url)->toBeNull();
});
