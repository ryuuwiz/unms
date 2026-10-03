<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\Router;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\CekStatusPembayaranInvoice;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    fakeXenditSession();
    $this->seed(RolesAndPermissionsSeeder::class);

    PengaturanGateway::create([
        'provider' => 'xendit',
        'nama' => 'Xendit',
        'credentials' => ['secret_key' => 'xnd_production_test', 'callback_token' => 'token-sah'],
        'is_default' => true,
        'sandbox_mode' => false,
    ]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => Pelanggan::factory(),
        'router_id' => Router::factory(),
        'paket_layanan_id' => PaketLayanan::factory()->create(['harga' => 300000, 'masa_aktif_nilai' => 1, 'masa_aktif_satuan' => 'bulan']),
        'tanggal_expired' => now()->toDateString(),
        'status' => StatusLayanan::Aktif,
    ]);

    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    $this->manager = app(PaymentGatewayManager::class);
});

/**
 * @return array<string, mixed>
 */
function sessionSelesai(TransaksiPaymentGateway $transaksi, ?float $amount = null): array
{
    return [
        'event' => 'payment_session.completed',
        'business_id' => 'biz-1',
        'created' => now()->toIso8601ZuluString(),
        'data' => [
            'payment_session_id' => $transaksi->xendit_reference_id,
            'reference_id' => $transaksi->external_id,
            'status' => 'COMPLETED',
            'amount' => $amount ?? (float) $transaksi->total_tagihan,
            'currency' => 'IDR',
            'payment_request_id' => 'pr-'.$transaksi->id,
            'payment_id' => 'py-'.$transaksi->id,
        ],
    ];
}

function kirimWebhookXendit(array $payload, string $token = 'token-sah'): void
{
    test()->withHeaders(['x-callback-token' => $token])
        ->postJson('/webhook/payment/xendit', $payload)
        ->assertOk();
}

test('payment_session.completed melunasi invoice dan metode transaksi tetap tercatat', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::Qris);

    kirimWebhookXendit(sessionSelesai($transaksi));

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Paid)
        ->and($transaksi->fresh()->channel)->toBe(GatewayChannel::Qris)
        ->and($this->invoice->pembayarans()->sole()->referensi_transaksi)->toBe('py-'.$transaksi->id);
});

test('nominal session yang tidak sama dengan total metode itu menjadi anomali, bukan pelunasan', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::VirtualAccount);

    kirimWebhookXendit(sessionSelesai($transaksi, amount: 304000));

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and(WebhookLog::sole()->catatan_error)->toContain('Anomali nominal');
});

test('payment_session.expired menandai transaksi kedaluwarsa sehingga tombol berikutnya membuat checkout baru', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::VirtualAccount);

    kirimWebhookXendit(['event' => 'payment_session.expired', 'data' => [...sessionSelesai($transaksi)['data'], 'status' => 'EXPIRED']]);

    expect($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Expired);
    expect($this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::VirtualAccount)->id)->not->toBe($transaksi->id);
});

test('pembayaran lewat checkout metode kedua setelah invoice lunas ditandai untuk penanganan manual', function () {
    $va = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::VirtualAccount);
    $qris = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::Qris);

    kirimWebhookXendit(sessionSelesai($va));
    kirimWebhookXendit(sessionSelesai($qris));

    $logQris = WebhookLog::where('transaksi_payment_gateway_id', $qris->id)->sole();
    expect($this->invoice->pembayarans()->count())->toBe(1)
        ->and($logQris->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($logQris->catatan_error)->toContain('sudah lunas');
});

test('cek status mendeteksi session yang sudah dibayar walau webhook-nya hilang', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::VirtualAccount);
    Http::swap(new Factory);
    Http::fake([
        'api.xendit.co/sessions/'.$transaksi->xendit_reference_id => Http::response([
            ...sessionSelesai($transaksi)['data'],
            'status' => 'COMPLETED',
        ]),
    ]);

    app(CekStatusPembayaranInvoice::class)->periksa($this->invoice);

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Lunas);
});

test('webhook dengan token salah ditolak dan tidak melunasi', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::VirtualAccount);

    $this->withHeaders(['x-callback-token' => 'token-palsu'])
        ->postJson('/webhook/payment/xendit', sessionSelesai($transaksi))
        ->assertUnauthorized();

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('payment.capture melunasi invoice, dan session.completed untuk payment_id yang sama tidak dicatat dua kali', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::VirtualAccount);
    $capture = [
        'event' => 'payment.capture',
        'business_id' => 'biz-1',
        'created' => now()->toIso8601ZuluString(),
        'data' => [
            'payment_id' => 'py-'.$transaksi->id,
            'payment_request_id' => 'pr-'.$transaksi->id,
            'reference_id' => $transaksi->external_id,
            'request_amount' => (float) $transaksi->total_tagihan,
            'currency' => 'IDR',
            'channel_code' => 'BCA_VIRTUAL_ACCOUNT',
            'status' => 'SUCCEEDED',
            'captures' => [['capture_id' => 'cptr-1', 'capture_amount' => (float) $transaksi->total_tagihan]],
        ],
    ];

    kirimWebhookXendit($capture);
    kirimWebhookXendit(sessionSelesai($transaksi));

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->pembayarans()->count())->toBe(1);
});

test('session tanpa reference_id tetap dicocokkan lewat payment_session_id', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::Qris);
    $payload = sessionSelesai($transaksi);
    unset($payload['data']['reference_id']);

    kirimWebhookXendit($payload);

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Lunas);
});

test('di produksi, transaksi dari koneksi sandbox tidak melunasi invoice', function () {
    PengaturanGateway::query()->update(['sandbox_mode' => true]);
    app()->detectEnvironment(fn () => 'production');
    $transaksi = $this->manager->buatPaymentLink($this->invoice, metode: GatewayChannel::VirtualAccount);

    kirimWebhookXendit(sessionSelesai($transaksi));

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('callback link lama /v2/invoices yang terbit sebelum migrasi tetap melunasi', function () {
    $lama = TransaksiPaymentGateway::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'xendit',
        'pengaturan_gateway_id' => PengaturanGateway::sole()->id,
        'external_id' => $this->invoice->no_invoice.'-1700000000',
        'xendit_reference_id' => '6abe1a0cad9cd3582f98b526',
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => 304000,
        'fee_gateway' => 4000,
        'status' => StatusTransaksiGateway::Pending,
    ]);

    kirimWebhookXendit([
        'id' => '6abe1a0cad9cd3582f98b526',
        'external_id' => $lama->external_id,
        'status' => 'PAID',
        'paid_amount' => 304000,
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BCA',
        'currency' => 'IDR',
    ]);

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($lama->fresh()->channel)->toBe(GatewayChannel::VirtualAccount);
});
