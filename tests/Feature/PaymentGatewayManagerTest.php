<?php

use App\DTO\PaymentGateway\PaymentCallbackData;
use App\DTO\PaymentGateway\PaymentLinkResponse;
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
use App\Services\Billing\InvoiceCetak;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    fakeXenditSession();
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->manager = app(PaymentGatewayManager::class);

    $this->xenditSetting = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Utama',
        'credentials' => [
            'secret_key' => 'xnd_development_test_123',
            'callback_token' => 'xendit_token_123',
        ],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ]);

    $this->ipaymuSetting = PengaturanGateway::create([
        'provider' => 'ipaymu',
        'gateway' => 'ipaymu',
        'nama' => 'iPaymu Official',
        'credentials' => [
            'va' => '11790012345678',
            'api_key' => 'IPAYMU_KEY_12345',
        ],
        'is_default' => false,
        'is_active' => true,
        'sandbox_mode' => true,
    ]);

    $this->pelanggan = Pelanggan::factory()->create([
        'no_reg' => 'BF2808202601',
        'email' => 'budi@example.com',
        'no_hp' => '08123456789',
    ]);

    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create([
        'harga' => 250000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);

    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'router_id' => $router->id,
        'paket_layanan_id' => $paket->id,
        'tanggal_expired' => now()->toDateString(),
        'status' => StatusLayanan::Aktif,
    ]);

    $this->invoice = Invoice::create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'periode_tagihan' => now()->format('Y-m'),
        'jumlah' => 250000,
        'jumlah_setelah_promo' => 250000,
        'tanggal_terbit' => now()->toDateString(),
        'tanggal_jatuh_tempo' => now()->addDays(7)->toDateString(),
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

test('manager dapat resolve driver xendit', function () {
    expect($this->manager->driver('xendit')->getProviderName())->toBe('xendit');
});

test('manager menolak resolve driver ipaymu karena belum didaftarkan', function () {
    expect(fn () => $this->manager->driver('ipaymu'))
        ->toThrow(InvalidArgumentException::class);
});

test('manager dapat membuat link pembayaran xendit dan menyimpan url di invoice', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    expect($trx)->toBeInstanceOf(TransaksiPaymentGateway::class)
        ->and($trx->gateway)->toBe('xendit')
        ->and($trx->status)->toBe(StatusTransaksiGateway::Pending);

    $this->invoice->refresh();
    expect($this->invoice->payment_gateway_url)->not->toBeNull()
        ->and($this->invoice->payment_gateway_provider)->toBe('xendit')
        ->and($this->invoice->hasActivePaymentLink())->toBeTrue();
});

test('kegagalan panggilan API gateway meninggalkan baris transaksi Pending sebagai jejak rekonsiliasi', function () {
    // Simulasikan timeout/exception dari Xendit SETELAH baris TransaksiPaymentGateway
    // direservasi lokal (lihat PaymentGatewayManager::buatPaymentLink). Baris Pending harus
    // tetap ada dengan nominal yang benar, dan invoice TIDAK boleh menunjuk payment_gateway_url
    // yang sebetulnya tidak pernah berhasil dibuat.
    $failingDriver = new class extends XenditDriver
    {
        public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting, ?string $externalId = null, ?GatewayChannel $metode = null, int $biayaAdmin = 0): PaymentLinkResponse
        {
            throw new Exception('Simulasi timeout Xendit');
        }
    };
    $this->manager->registerDriver('xendit', $failingDriver);

    expect(fn () => $this->manager->buatPaymentLink($this->invoice, 'xendit'))
        ->toThrow(Exception::class, 'Simulasi timeout Xendit');

    $trx = TransaksiPaymentGateway::where('invoice_id', $this->invoice->id)->first();
    expect($trx)->not->toBeNull()
        ->and($trx->status)->toBe(StatusTransaksiGateway::Pending)
        ->and((float) $trx->total_tagihan)->toBe(264430.0); // 250000 + fee VA (9000 + 4000) x 1,11

    $this->invoice->refresh();
    expect($this->invoice->payment_gateway_url)->toBeNull();
});

test('invoice gateway yang tidak ditemukan ditandai invalid agar dapat diterbitkan ulang', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $missingInvoiceDriver = new class extends XenditDriver
    {
        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            return ['error' => 'Could not find invoice by id 6abe1e4ade2f5074a6d5f3c1'];
        }
    };
    $this->manager->registerDriver('xendit', $missingInvoiceDriver);

    $result = $this->manager->sinkronkanStatus($this->invoice);

    $this->invoice->refresh();
    $transaksi->refresh();

    expect($result['error'])->toContain('Could not find invoice by id')
        ->and($this->invoice->payment_gateway_id)->toBeNull()
        ->and($this->invoice->payment_gateway_url)->toBeNull()
        ->and($this->invoice->payment_gateway_status)->toBe('EXPIRED')
        // "Tidak ditemukan" bukan pernyataan kedaluwarsa dari gateway (ADR-0067).
        ->and($transaksi->status)->toBe(StatusTransaksiGateway::Pending);
});

test('invoice lunas dapat dipulihkan melalui external_id setelah id gateway tidak ditemukan', function () {
    $transaksi = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $recoveredDriver = new class extends XenditDriver
    {
        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            return [
                'id' => '6abe1a0cad9cd3582f98b526',
                'external_id' => $target instanceof TransaksiPaymentGateway ? $target->external_id : 'unused',
                'invoice_url' => 'https://checkout.xendit.co/web/6abe1a0cad9cd3582f98b526',
                'status' => 'PAID',
                'amount' => (float) ($target instanceof TransaksiPaymentGateway ? $target->total_tagihan : 0),
                'paid_amount' => (float) ($target instanceof TransaksiPaymentGateway ? $target->total_tagihan : 0),
                'recovered_by' => 'external_id',
            ];
        }
    };
    $this->manager->registerDriver('xendit', $recoveredDriver);

    $this->invoice->update([
        'payment_gateway_id' => '6abe1e4ade2f5074a6d5f3c1',
        'xendit_invoice_id' => '6abe1e4ade2f5074a6d5f3c1',
    ]);
    $result = $this->manager->sinkronkanStatus($this->invoice);

    $this->invoice->refresh();
    expect($result['recovered_by'])->toBe('external_id')
        ->and($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->payment_gateway_id)->toBe('6abe1a0cad9cd3582f98b526')
        ->and($transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Paid);
});

test('proses pelunasan memperbarui status invoice, layanan, dan memancarkan event InvoicePaidEvent', function () {
    Event::fake([InvoicePaidEvent::class]);

    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $callbackData = new PaymentCallbackData(
        provider: 'xendit',
        externalId: (string) $trx->external_id,
        status: 'PAID',
        paidAmount: (float) $trx->total_tagihan,
        eventId: 'xnd_trx_999',
        paidAt: now()->toIso8601String(),
        channel: GatewayChannel::VirtualAccount,
        channelDetail: 'BCA',
        paymentReference: 'REF-XND-999',
        rawPayload: ['mock' => true]
    );

    $result = $this->manager->prosesPelunasan($this->invoice, $callbackData, $trx);

    expect($result)->toBeTrue();

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->payment_gateway_status)->toBe('PAID')
        ->and($this->invoice->pembayarans)->toHaveCount(1);

    $this->layanan->refresh();
    expect($this->layanan->status)->toBe(StatusLayanan::Aktif)
        ->and($this->layanan->tanggal_expired?->toDateString())->toBe(now()->addMonthNoOverflow()->day(10)->toDateString());

    expect(InvoiceCetak::dari($this->invoice->fresh())->metode)->toBe('Virtual Account BCA');

    Event::assertDispatched(InvoicePaidEvent::class);
});

test('xendit driver checkPaymentRequestV3Status dapat memproses respon v3 dengan benar', function () {
    Http::fake([
        'https://api.xendit.co/v3/payment_requests/pr-123456789' => Http::response([
            'id' => 'pr-123456789',
            'reference_id' => 'INV-TEST-001',
            'status' => 'SUCCEEDED',
            'amount' => 250000,
            'capture_amount' => 250000,
            'currency' => 'IDR',
            'payment_method' => [
                'type' => 'QR_CODE',
                'qr_code' => [
                    'channel_code' => 'QRIS',
                ],
            ],
        ], 200),
    ]);

    /** @var XenditDriver $driver */
    $driver = $this->manager->driver('xendit');
    $result = $driver->checkPaymentRequestV3Status('pr-123456789', 'xnd_development_test_123');

    expect($result)->toBeArray()
        ->and($result['status'])->toBe('PAID')
        ->and($result['paid_amount'])->toBe(250000.0)
        ->and($result['id'])->toBe('pr-123456789');
});
