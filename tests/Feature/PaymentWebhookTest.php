<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Jobs\PaymentGateway\ProcessPaymentWebhookJob;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\Router;
use App\Models\WebhookLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([InvoicePaidEvent::class]);

    $this->manager = pakaiGatewayUji();

    $this->gatewaySetting = PengaturanGateway::create([
        'provider' => 'uji',
        'gateway' => 'uji',
        'nama' => 'Gateway Uji',
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

test('webhook gateway memproses pelunasan invoice dengan benar', function () {
    Event::fake([InvoicePaidEvent::class]);

    $trx = $this->manager->buatPaymentLink($this->invoice, 'uji');

    $payload = [
        'id' => 'xnd_inv_test_123',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => $trx->total_tagihan,
        'paid_amount' => $trx->total_tagihan,
        'payment_method' => 'VIRTUAL_ACCOUNT',
        'payment_channel' => 'BCA',
        'paid_at' => now()->toIso8601String(),
    ];

    $response = $this->withHeaders([
        'x-callback-token' => 'test_callback_token',
    ])->postJson('/webhook/payment/uji', $payload);

    $response->assertOk()
        ->assertJson([
            'message' => 'Webhook received and queued for processing',
            'event_id' => 'xnd_inv_test_123',
            'status' => 'QUEUED',
        ]);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->payment_gateway_status)->toBe('PAID');

    Event::assertDispatched(InvoicePaidEvent::class);
});

test('webhook gateway mendispatch ProcessPaymentWebhookJob ke antrean payments terdedikasi', function () {
    Queue::fake();

    $trx = $this->manager->buatPaymentLink($this->invoice, 'uji');

    $payload = [
        'id' => 'xnd_inv_queue_test_1',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => $trx->total_tagihan,
        'paid_amount' => $trx->total_tagihan,
        'paid_at' => now()->toIso8601String(),
    ];

    $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/uji', $payload)
        ->assertOk();

    Queue::assertPushedOn('payments', ProcessPaymentWebhookJob::class);
});

test('webhook ipaymu tanpa X-Signature yang valid ditolak', function () {
    Event::fake([InvoicePaidEvent::class]);

    $payload = [
        'trx_id' => '12345678',
        'sid' => 'ipm_sess_test_123',
        'reference_id' => 'any-reference-id',
        'status' => 'berhasil',
        'status_code' => 1,
    ];

    $response = $this->postJson('/webhook/payment/ipaymu', $payload);

    $response->assertStatus(401);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran);

    Event::assertNotDispatched(InvoicePaidEvent::class);
});

test('webhook gateway menolak token salah dengan 401 dan tidak mengubah invoice', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'uji');

    $payload = [
        'id' => 'xnd_inv_wrong_token',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => $trx->total_tagihan,
        'paid_amount' => $trx->total_tagihan,
    ];

    $response = $this->withHeaders(['x-callback-token' => 'token-salah'])
        ->postJson('/webhook/payment/uji', $payload);

    $response->assertStatus(401);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('webhook gateway menolak request tanpa header token sama sekali dengan 401', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'uji');

    $payload = [
        'id' => 'xnd_inv_no_token',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => $trx->total_tagihan,
        'paid_amount' => $trx->total_tagihan,
    ];

    $response = $this->postJson('/webhook/payment/uji', $payload);

    $response->assertStatus(401);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('webhook menolak gateway yang tidak dikenal dengan 400', function () {
    $response = $this->postJson('/webhook/payment/gateway-tidak-ada', ['status' => 'PAID']);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Unsupported gateway: gateway-tidak-ada']);
});

test('webhook menolak callback berulang secara idempoten', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'uji');

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
        ->postJson('/webhook/payment/uji', $payload);
    $response1->assertOk();

    // Request kedua (callback retry dari gateway)
    $response2 = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/uji', $payload);
    $response2->assertOk()
        ->assertJson(['message' => 'Webhook already processed']);
});

test('webhook gateway dengan token tidak valid tetap membuat WebhookLog beraudit berstatus gagal', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'uji');

    $this->withHeaders(['x-callback-token' => 'token-salah'])
        ->postJson('/webhook/payment/uji', [
            'id' => 'xnd_audit_reject_test',
            'external_id' => $trx->external_id,
            'status' => 'PAID',
        ])
        ->assertStatus(401);

    $log = WebhookLog::where('event_type', 'webhook.token_rejected')
        ->where('provider', 'uji')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status_proses)->toBe(StatusWebhookLog::Gagal);
});

test('permintaan webhook payment melebihi batas rate limit menerima 429', function () {
    for ($i = 0; $i < 120; $i++) {
        $this->withHeaders(['x-callback-token' => 'token-salah'])
            ->postJson('/webhook/payment/uji', ['id' => "rl_payment_test_{$i}"])
            ->assertStatus(401);
    }

    $this->withHeaders(['x-callback-token' => 'token-salah'])
        ->postJson('/webhook/payment/uji', ['id' => 'rl_payment_test_over'])
        ->assertStatus(429);
});
