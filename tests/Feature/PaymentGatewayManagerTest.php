<?php

use App\DTO\PaymentGateway\PaymentCallbackData;
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
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
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

test('manager dapat resolve driver xendit dan ipaymu', function () {
    expect($this->manager->driver('xendit')->getProviderName())->toBe('xendit')
        ->and($this->manager->driver('ipaymu')->getProviderName())->toBe('ipaymu');
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

test('manager dapat membuat link pembayaran ipaymu dan menyimpan url di invoice', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'ipaymu');

    expect($trx)->toBeInstanceOf(TransaksiPaymentGateway::class)
        ->and($trx->gateway)->toBe('ipaymu')
        ->and($trx->status)->toBe(StatusTransaksiGateway::Pending);

    $this->invoice->refresh();
    expect($this->invoice->payment_gateway_url)->not->toBeNull()
        ->and($this->invoice->payment_gateway_provider)->toBe('ipaymu')
        ->and($this->invoice->hasActivePaymentLink())->toBeTrue();
});

test('proses pelunasan memperbarui status invoice, layanan, dan memancarkan event InvoicePaidEvent', function () {
    Event::fake([InvoicePaidEvent::class]);

    $trx = $this->manager->buatPaymentLink($this->invoice, 'ipaymu');

    $callbackData = new PaymentCallbackData(
        provider: 'ipaymu',
        externalId: (string) $trx->external_id,
        status: 'PAID',
        paidAmount: 250000,
        eventId: 'ipm_trx_999',
        paidAt: now()->toIso8601String(),
        channel: GatewayChannel::VirtualAccount,
        channelDetail: 'BCA',
        paymentReference: 'REF-IPM-999',
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
        ->and($this->layanan->tanggal_expired?->toDateString())->toBe(now()->addMonth()->toDateString());

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
