<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Events\InvoicePaidEvent;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\Router;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([InvoicePaidEvent::class]);

    $this->manager = app(PaymentGatewayManager::class);

    $this->xenditSetting = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Utama',
        'credentials' => [
            'secret_key' => 'xnd_development_test_123',
            'callback_token' => 'test_callback_token',
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
    ]);

    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create([
        'harga' => 300000,
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
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'tanggal_terbit' => now()->toDateString(),
        'tanggal_jatuh_tempo' => now()->addDays(7)->toDateString(),
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

test('webhook xendit memproses pelunasan invoice dengan benar', function () {
    Event::fake([InvoicePaidEvent::class]);

    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $payload = [
        'id' => 'xnd_inv_test_123',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => 300000,
        'paid_amount' => 300000,
        'payment_method' => 'VIRTUAL_ACCOUNT',
        'payment_channel' => 'BCA',
        'paid_at' => now()->toIso8601String(),
    ];

    $response = $this->withHeaders([
        'x-callback-token' => 'test_callback_token',
    ])->postJson('/webhook/payment/xendit', $payload);

    $response->assertOk()
        ->assertJson([
            'message' => 'Payment processed successfully',
            'external_id' => $trx->external_id,
        ]);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->payment_gateway_status)->toBe('PAID');

    Event::assertDispatched(InvoicePaidEvent::class);
});

test('webhook ipaymu memproses pelunasan invoice dengan benar', function () {
    Event::fake([InvoicePaidEvent::class]);

    $trx = $this->manager->buatPaymentLink($this->invoice, 'ipaymu');

    $payload = [
        'trx_id' => '12345678',
        'sid' => 'ipm_sess_test_123',
        'reference_id' => $trx->external_id,
        'status' => 'berhasil',
        'status_code' => 1,
        'total' => 300000,
        'fee' => 3000,
        'via' => 'qris',
        'channel' => 'qris',
    ];

    $response = $this->postJson('/webhook/payment/ipaymu', $payload);

    $response->assertOk()
        ->assertJson([
            'message' => 'Payment processed successfully',
            'external_id' => $trx->external_id,
        ]);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->payment_gateway_status)->toBe('PAID');

    Event::assertDispatched(InvoicePaidEvent::class);
});

test('webhook menolak callback berulang secara idempoten', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $payload = [
        'id' => 'xnd_inv_idempotent_123',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => 300000,
        'paid_amount' => 300000,
        'payment_method' => 'QRIS',
        'paid_at' => now()->toIso8601String(),
    ];

    // Request pertama
    $response1 = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $payload);
    $response1->assertOk();

    // Request kedua (callback retry dari gateway)
    $response2 = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $payload);
    $response2->assertOk()
        ->assertJson(['message' => 'Webhook already processed']);
});
