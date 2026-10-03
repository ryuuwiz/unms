<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Events\InvoicePaidEvent;
use App\Livewire\Invoice\Show as InvoiceShow;
use App\Livewire\Pembayaran\TransaksiGateway\Show as TransaksiGatewayShow;
use App\Livewire\Portal\Invoice\Show as PortalInvoiceShow;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Support\GatewayUjiDriver;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([InvoicePaidEvent::class]);

    $this->koneksi = PengaturanGateway::create([
        'provider' => 'uji',
        'gateway' => 'uji',
        'nama' => 'Gateway Uji Produksi',
        'credentials' => ['secret_key' => 'xnd_production_a', 'callback_token' => 'token-a'],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => false,
    ]);

    $this->pelanggan = Pelanggan::factory()->create();
    $paket = PaketLayanan::factory()->create(['harga' => 150000, 'masa_aktif_nilai' => 1, 'masa_aktif_satuan' => 'bulan']);
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'tanggal_expired' => now()->subDays(10)->toDateString(),
        'status' => StatusLayanan::Suspend,
    ]);

    // Status gateway per provider_reference_id yang dikembalikan driver palsu; selain itu EXPIRED.
    $this->statusGateway = [];
    $this->dicek = [];
    $manager = app(PaymentGatewayManager::class);
    $test = $this;
    $manager->registerDriver('uji', new class($test) extends GatewayUjiDriver
    {
        public function __construct(private $test) {}

        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            $id = $target instanceof TransaksiPaymentGateway ? $target->provider_reference_id : $target->payment_gateway_id;
            $this->test->dicek[] = $id;

            return $this->test->statusGateway[$id] ?? ['id' => $id, 'status' => 'EXPIRED'];
        }
    });
    $this->app->instance(PaymentGatewayManager::class, $manager);

    $this->staf = User::factory()->create();
    $this->staf->assignRole('admin');
});

function invoiceCekStatus(LayananPelanggan $layanan, array $atribut = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 150000,
        'jumlah_setelah_promo' => 150000,
        'tanggal_terbit' => now()->subDays(20)->toDateString(),
        'tanggal_jatuh_tempo' => now()->subDays(13)->toDateString(),
        'status' => StatusInvoice::MenungguPembayaran,
    ], $atribut));
}

function transaksiCekStatus(Invoice $invoice, PengaturanGateway $koneksi, string $ref, StatusTransaksiGateway $status = StatusTransaksiGateway::Expired): TransaksiPaymentGateway
{
    return TransaksiPaymentGateway::create([
        'invoice_id' => $invoice->id,
        'pengaturan_gateway_id' => $koneksi->id,
        'gateway' => 'uji',
        'external_id' => $invoice->no_invoice.'-'.$ref,
        'provider_reference_id' => $ref,
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => $invoice->jumlah_setelah_promo,
        'fee_gateway' => 0,
        'status' => $status,
    ]);
}

function lunasDiGateway(TransaksiPaymentGateway $transaksi, float $nominal = 150000): array
{
    return [
        'id' => $transaksi->provider_reference_id,
        'external_id' => $transaksi->external_id,
        'status' => 'PAID',
        'amount' => $nominal,
        'paid_amount' => $nominal,
        'paid_at' => '2026-09-25T03:15:00.000Z',
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BNI',
        'currency' => 'IDR',
    ];
}

/**
 * Invoice dengan link lama yang sudah dibayar dan link terbaru yang masih Pending.
 *
 * @return array{0: Invoice, 1: TransaksiPaymentGateway}
 */
function invoiceDibayarLewatLinkLama(object $test): array
{
    $invoice = invoiceCekStatus($test->layanan);
    $linkLama = transaksiCekStatus($invoice, $test->koneksi, 'inv_lama');
    transaksiCekStatus($invoice, $test->koneksi, 'inv_baru', StatusTransaksiGateway::Pending);
    $test->statusGateway['inv_lama'] = lunasDiGateway($linkLama);

    return [$invoice, $linkLama];
}

test('tombol di detail Invoice melunasi invoice yang dibayar lewat link lama', function () {
    [$invoice, $linkLama] = invoiceDibayarLewatLinkLama($this);

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaran')
        ->assertSee('Hasil cek status pembayaran gateway: Lunas');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($linkLama->fresh()->status)->toBe(StatusTransaksiGateway::Paid)
        ->and($this->dicek)->toBe(['inv_baru', 'inv_lama']);
});

test('tombol Rekonsiliasi di detail Transaksi Gateway melunasi invoice yang dibayar lewat link lama', function () {
    [$invoice] = invoiceDibayarLewatLinkLama($this);
    $transaksiTerbaru = $invoice->transaksiPaymentGateways()->latest('id')->first();

    Livewire::actingAs($this->staf)
        ->test(TransaksiGatewayShow::class, ['transaksi' => $transaksiTerbaru])
        ->call('cekStatusPembayaran')
        ->assertSee('Hasil cek status pembayaran gateway: Lunas');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas);
});

test('tombol Cek Status Pembayaran di Portal melunasi invoice yang dibayar lewat link lama', function () {
    [$invoice] = invoiceDibayarLewatLinkLama($this);

    Livewire::actingAs($this->pelanggan->akunPelanggan, 'pelanggan')
        ->test(PortalInvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaran')
        ->assertDispatched('toast-show', fn (string $event, array $params) => $params['slots']['text'] === 'Pembayaran berhasil terkonfirmasi! Tagihan telah lunas.');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas);
});

test('membuka Portal melunasi invoice yang dibayar lewat link lama yang masih Pending, tanpa menanyakan link kedaluwarsa', function () {
    $invoice = invoiceCekStatus($this->layanan, ['payment_gateway_id' => 'inv_baru']);
    transaksiCekStatus($invoice, $this->koneksi, 'inv_kedaluwarsa');
    $linkLama = transaksiCekStatus($invoice, $this->koneksi, 'inv_lama', StatusTransaksiGateway::Pending);
    transaksiCekStatus($invoice, $this->koneksi, 'inv_baru', StatusTransaksiGateway::Pending);
    $this->statusGateway['inv_baru'] = ['id' => 'inv_baru', 'status' => 'PENDING'];
    $this->statusGateway['inv_lama'] = lunasDiGateway($linkLama);

    Livewire::actingAs($this->pelanggan->akunPelanggan, 'pelanggan')
        ->test(PortalInvoiceShow::class, ['invoice' => $invoice])
        ->assertOk();

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($this->dicek)->toBe(['inv_baru', 'inv_lama']);
});

test('membuka Portal untuk invoice yang semua link-nya kedaluwarsa tidak menanyakan gateway', function () {
    $invoice = invoiceCekStatus($this->layanan, ['payment_gateway_id' => 'inv_kedaluwarsa']);
    transaksiCekStatus($invoice, $this->koneksi, 'inv_kedaluwarsa');

    Livewire::actingAs($this->pelanggan->akunPelanggan, 'pelanggan')
        ->test(PortalInvoiceShow::class, ['invoice' => $invoice])
        ->assertOk();

    expect($this->dicek)->toBe([]);
});

test('invoice Digabung yang sudah dibayar tidak dilunasi otomatis: staf melihat alasannya, Portal hanya pesan umum', function () {
    $oktober = invoiceCekStatus($this->layanan, ['periode_tagihan' => '2026-10', 'jumlah_setelah_promo' => 300000, 'jumlah_tunggakan' => 150000]);
    $september = invoiceCekStatus($this->layanan, ['periode_tagihan' => '2026-09', 'status' => StatusInvoice::Digabung, 'digabung_ke_invoice_id' => $oktober->id]);
    $trxSeptember = transaksiCekStatus($september, $this->koneksi, 'inv_sep');
    $this->statusGateway['inv_sep'] = lunasDiGateway($trxSeptember);

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $september])
        ->call('cekStatusPembayaran')
        ->assertSee('Perlu diproses manual')
        ->assertSee('periksa dan proses manual');

    Livewire::actingAs($this->staf)
        ->test(TransaksiGatewayShow::class, ['transaksi' => $trxSeptember])
        ->call('cekStatusPembayaran')
        ->assertSee('Perlu diproses manual');

    Livewire::actingAs($this->pelanggan->akunPelanggan, 'pelanggan')
        ->test(PortalInvoiceShow::class, ['invoice' => $september])
        ->call('cekStatusPembayaran')
        ->assertDispatched('toast-show', fn (string $event, array $params) => $params['slots']['text'] === 'Pembayaran Anda sedang kami periksa. Tim kami akan menghubungi Anda.')
        ->assertDontSee('periksa dan proses manual');

    expect($september->fresh()->status)->toBe(StatusInvoice::Digabung)
        ->and($trxSeptember->fresh()->status)->toBe(StatusTransaksiGateway::Expired)
        ->and((float) $oktober->fresh()->jumlah_setelah_promo)->toBe(300000.0);
});

test('pembayaran dengan nominal tidak sama tidak dilunasi: staf melihat alasannya, Portal hanya pesan umum', function () {
    $invoice = invoiceCekStatus($this->layanan);
    $this->statusGateway['inv_kurang'] = lunasDiGateway(transaksiCekStatus($invoice, $this->koneksi, 'inv_kurang'), 99000);

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaran')
        ->assertSee('Perlu diproses manual')
        ->assertSee('Nominal tidak sama');

    Livewire::actingAs($this->pelanggan->akunPelanggan, 'pelanggan')
        ->test(PortalInvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaran')
        ->assertDispatched('toast-show', fn (string $event, array $params) => $params['slots']['text'] === 'Pembayaran Anda sedang kami periksa. Tim kami akan menghubungi Anda.')
        ->assertDontSee('Nominal tidak sama');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('invoice yang belum dibayar di semua link tampil belum dibayar', function () {
    $invoice = invoiceCekStatus($this->layanan);
    transaksiCekStatus($invoice, $this->koneksi, 'inv_a');
    transaksiCekStatus($invoice, $this->koneksi, 'inv_b', StatusTransaksiGateway::Pending);

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaran')
        ->assertSee('Hasil cek status pembayaran gateway: Belum dibayar');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($this->dicek)->toBe(['inv_b', 'inv_a']);
});

test('tombol cek status di detail Invoice hanya untuk pengguna dengan izin pembayaran.lihat', function () {
    $invoice = invoiceCekStatus($this->layanan);
    $tanpaIzin = User::factory()->create();
    $tanpaIzin->givePermissionTo('invoice.lihat');

    Livewire::actingAs($tanpaIzin)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->assertDontSee('Cek Status Pembayaran')
        ->call('cekStatusPembayaran')
        ->assertForbidden();

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->assertSee('Cek Status Pembayaran');
});

test('tombol Cek Status Pembayaran di Portal dibatasi agar tidak membanjiri gateway', function () {
    $invoice = invoiceCekStatus($this->layanan);
    transaksiCekStatus($invoice, $this->koneksi, 'inv_a');
    $portal = Livewire::actingAs($this->pelanggan->akunPelanggan, 'pelanggan')->test(PortalInvoiceShow::class, ['invoice' => $invoice]);

    foreach (range(1, 5) as $ignored) {
        $portal->call('cekStatusPembayaran');
    }
    $portal->call('cekStatusPembayaran')
        ->assertDispatched('toast-show', fn (string $event, array $params) => str_contains($params['slots']['text'], 'Terlalu sering'));

    expect($this->dicek)->toHaveCount(5);
});
