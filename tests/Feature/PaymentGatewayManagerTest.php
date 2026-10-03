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
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\GatewayUjiDriver;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->manager = pakaiGatewayUji();

    $this->gatewaySetting = PengaturanGateway::create([
        'provider' => 'uji',
        'gateway' => 'uji',
        'nama' => 'Gateway Uji',
        'credentials' => [
            'secret_key' => 'xnd_development_test_123',
            'callback_token' => 'uji_token_123',
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

test('manager dapat resolve driver ipaymu', function () {
    expect($this->manager->driver('ipaymu')->getProviderName())->toBe('ipaymu');
});

test('manager dapat membuat link pembayaran dan menyimpan url di invoice', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'uji');

    expect($trx)->toBeInstanceOf(TransaksiPaymentGateway::class)
        ->and($trx->gateway)->toBe('uji')
        ->and($trx->status)->toBe(StatusTransaksiGateway::Pending);

    $this->invoice->refresh();
    expect($this->invoice->payment_gateway_url)->not->toBeNull()
        ->and($this->invoice->payment_gateway_provider)->toBe('uji')
        ->and($this->invoice->hasActivePaymentLink())->toBeTrue();
});

test('kegagalan panggilan API gateway meninggalkan baris transaksi Pending sebagai jejak rekonsiliasi', function () {
    // Simulasikan timeout/exception dari gateway SETELAH baris TransaksiPaymentGateway
    // direservasi lokal (lihat PaymentGatewayManager::buatPaymentLink). Baris Pending harus
    // tetap ada dengan nominal yang benar, dan invoice TIDAK boleh menunjuk payment_gateway_url
    // yang sebetulnya tidak pernah berhasil dibuat.
    $failingDriver = new class extends GatewayUjiDriver
    {
        public function createPaymentLink(Invoice $invoice, PengaturanGateway $setting, ?string $externalId = null): PaymentLinkResponse
        {
            throw new Exception('Simulasi timeout gateway');
        }
    };
    $this->manager->registerDriver('uji', $failingDriver);

    expect(fn () => $this->manager->buatPaymentLink($this->invoice, 'uji'))
        ->toThrow(Exception::class, 'Simulasi timeout gateway');

    $trx = TransaksiPaymentGateway::where('invoice_id', $this->invoice->id)->first();
    expect($trx)->not->toBeNull()
        ->and($trx->status)->toBe(StatusTransaksiGateway::Pending)
        ->and((float) $trx->total_tagihan)->toBe(250000.0); // Hosted Invoice tanpa Fee Admin lokal (ADR-0072)

    $this->invoice->refresh();
    expect($this->invoice->payment_gateway_url)->toBeNull();
});

test('proses pelunasan memperbarui status invoice, layanan, dan memancarkan event InvoicePaidEvent', function () {
    Event::fake([InvoicePaidEvent::class]);

    $trx = $this->manager->buatPaymentLink($this->invoice, 'uji');

    $callbackData = new PaymentCallbackData(
        provider: 'uji',
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
