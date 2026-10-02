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
use App\Models\KasusPelunasanSusulan;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

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

    $this->pelanggan = Pelanggan::factory()->create();
    $paket = PaketLayanan::factory()->create(['harga' => 150000, 'masa_aktif_nilai' => 1, 'masa_aktif_satuan' => 'bulan']);
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'tanggal_expired' => now()->subDays(10)->toDateString(),
        'status' => StatusLayanan::Suspend,
    ]);

    // Status Xendit per xendit_reference_id yang dikembalikan driver palsu; selain itu EXPIRED.
    $this->statusXendit = [];
    $this->dicek = [];
    $manager = app(PaymentGatewayManager::class);
    $test = $this;
    $manager->registerDriver('xendit', new class($test) extends XenditDriver
    {
        public function __construct(private $test) {}

        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            $id = $target instanceof TransaksiPaymentGateway ? $target->xendit_reference_id : $target->xendit_invoice_id;
            $this->test->dicek[] = $id;

            return $this->test->statusXendit[$id] ?? ['id' => $id, 'status' => 'EXPIRED'];
        }

        public function kedaluwarsakanInvoice(TransaksiPaymentGateway $transaksi, PengaturanGateway $setting): void {}
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
        'gateway' => 'xendit',
        'external_id' => $invoice->no_invoice.'-'.$ref,
        'xendit_reference_id' => $ref,
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => $invoice->jumlah_setelah_promo,
        'fee_gateway' => 0,
        'status' => $status,
    ]);
}

function lunasDiXendit(TransaksiPaymentGateway $transaksi, float $nominal = 150000): array
{
    return [
        'id' => $transaksi->xendit_reference_id,
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
    $test->statusXendit['inv_lama'] = lunasDiXendit($linkLama);

    return [$invoice, $linkLama];
}

test('tombol di detail Invoice melunasi invoice yang dibayar lewat link lama', function () {
    [$invoice, $linkLama] = invoiceDibayarLewatLinkLama($this);

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaranXendit')
        ->assertSee('Hasil cek status pembayaran Xendit: Lunas');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($linkLama->fresh()->status)->toBe(StatusTransaksiGateway::Paid)
        ->and($this->dicek)->toBe(['inv_baru', 'inv_lama']);
});

test('tombol Rekonsiliasi di detail Transaksi Gateway melunasi invoice yang dibayar lewat link lama', function () {
    [$invoice] = invoiceDibayarLewatLinkLama($this);
    $transaksiTerbaru = $invoice->transaksiPaymentGateways()->latest('id')->first();

    Livewire::actingAs($this->staf)
        ->test(TransaksiGatewayShow::class, ['transaksi' => $transaksiTerbaru])
        ->call('cekStatusPembayaranXendit')
        ->assertSee('Hasil cek status pembayaran Xendit: Lunas');

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

test('invoice Digabung yang dibayar mengikuti aturan Pelunasan Susulan', function () {
    $oktober = invoiceCekStatus($this->layanan, [
        'periode_tagihan' => '2026-10',
        'jumlah_setelah_promo' => 300000,
        'jumlah_tunggakan' => 150000,
    ]);
    $september = invoiceCekStatus($this->layanan, [
        'periode_tagihan' => '2026-09',
        'status' => StatusInvoice::Digabung,
        'digabung_ke_invoice_id' => $oktober->id,
    ]);
    $this->statusXendit['inv_sep'] = lunasDiXendit(transaksiCekStatus($september, $this->koneksi, 'inv_sep'));

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $september])
        ->call('cekStatusPembayaranXendit')
        ->assertSee('Lunas');

    expect($september->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($september->fresh()->digabung_ke_invoice_id)->toBeNull()
        ->and((float) $oktober->fresh()->jumlah_setelah_promo)->toBe(150000.0);
});

test('pembayaran yang dilaporkan tersimpan sebagai kasus: staf melihat alasannya, Portal hanya pesan umum', function () {
    $invoice = invoiceCekStatus($this->layanan);
    $this->statusXendit['inv_kurang'] = lunasDiXendit(transaksiCekStatus($invoice, $this->koneksi, 'inv_kurang'), 99000);

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaranXendit')
        ->assertSee('Dilaporkan untuk tindakan manual')
        ->assertSee('Nominal tidak sama');

    Livewire::actingAs($this->pelanggan->akunPelanggan, 'pelanggan')
        ->test(PortalInvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaran')
        ->assertDispatched('toast-show', fn (string $event, array $params) => $params['slots']['text'] === 'Pembayaran Anda sedang kami periksa. Tim kami akan menghubungi Anda.')
        ->assertDontSee('Nominal tidak sama');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and(KasusPelunasanSusulan::count())->toBe(1);
});

test('invoice yang belum dibayar di semua link tampil belum dibayar', function () {
    $invoice = invoiceCekStatus($this->layanan);
    transaksiCekStatus($invoice, $this->koneksi, 'inv_a');
    transaksiCekStatus($invoice, $this->koneksi, 'inv_b', StatusTransaksiGateway::Pending);

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->call('cekStatusPembayaranXendit')
        ->assertSee('Hasil cek status pembayaran Xendit: Belum dibayar');

    expect($invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($this->dicek)->toBe(['inv_b', 'inv_a']);
});

test('tombol cek status di detail Invoice hanya untuk pengguna dengan izin pembayaran.lihat', function () {
    $invoice = invoiceCekStatus($this->layanan);
    $tanpaIzin = User::factory()->create();
    $tanpaIzin->givePermissionTo('invoice.lihat');

    Livewire::actingAs($tanpaIzin)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->assertDontSee('Cek Status Pembayaran Xendit')
        ->call('cekStatusPembayaranXendit')
        ->assertForbidden();

    Livewire::actingAs($this->staf)
        ->test(InvoiceShow::class, ['invoice' => $invoice])
        ->assertSee('Cek Status Pembayaran Xendit');
});

test('tombol Rekonsiliasi di detail Transaksi Gateway mengikuti aturan Digabung dan menampilkan alasan kasus', function () {
    $oktober = invoiceCekStatus($this->layanan, ['periode_tagihan' => '2026-10', 'jumlah_setelah_promo' => 300000, 'jumlah_tunggakan' => 150000]);
    $september = invoiceCekStatus($this->layanan, ['periode_tagihan' => '2026-09', 'status' => StatusInvoice::Digabung, 'digabung_ke_invoice_id' => $oktober->id]);
    $trxSeptember = transaksiCekStatus($september, $this->koneksi, 'inv_sep');
    $this->statusXendit['inv_sep'] = lunasDiXendit($trxSeptember);

    Livewire::actingAs($this->staf)
        ->test(TransaksiGatewayShow::class, ['transaksi' => $trxSeptember])
        ->call('cekStatusPembayaranXendit')
        ->assertSee('Hasil cek status pembayaran Xendit: Lunas');

    expect($september->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and((float) $oktober->fresh()->jumlah_setelah_promo)->toBe(150000.0);

    $kurang = invoiceCekStatus($this->layanan, ['periode_tagihan' => '2026-08']);
    $trxKurang = transaksiCekStatus($kurang, $this->koneksi, 'inv_kurang');
    $this->statusXendit['inv_kurang'] = lunasDiXendit($trxKurang, 99000);

    Livewire::actingAs($this->staf)
        ->test(TransaksiGatewayShow::class, ['transaksi' => $trxKurang])
        ->call('cekStatusPembayaranXendit')
        ->assertSee('Dilaporkan untuk tindakan manual')
        ->assertSee('Nominal tidak sama');
});

test('tombol Cek Status Pembayaran di Portal dibatasi agar tidak membanjiri Xendit', function () {
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
