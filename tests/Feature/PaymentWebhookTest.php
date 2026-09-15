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
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

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
        'amount' => $trx->total_tagihan,
        'paid_amount' => $trx->total_tagihan,
        'payment_method' => 'VIRTUAL_ACCOUNT',
        'payment_channel' => 'BCA',
        'paid_at' => now()->toIso8601String(),
    ];

    $response = $this->withHeaders([
        'x-callback-token' => 'test_callback_token',
    ])->postJson('/webhook/payment/xendit', $payload);

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

test('webhook xendit mendispatch ProcessPaymentWebhookJob ke antrean payments terdedikasi', function () {
    Queue::fake();

    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $payload = [
        'id' => 'xnd_inv_queue_test_1',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => $trx->total_tagihan,
        'paid_amount' => $trx->total_tagihan,
        'paid_at' => now()->toIso8601String(),
    ];

    $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $payload)
        ->assertOk();

    Queue::assertPushedOn('payments', ProcessPaymentWebhookJob::class);
});

test('webhook ipaymu ditolak karena driver belum didaftarkan (verifikasi signature belum ada)', function () {
    Event::fake([InvoicePaidEvent::class]);

    $payload = [
        'trx_id' => '12345678',
        'sid' => 'ipm_sess_test_123',
        'reference_id' => 'any-reference-id',
        'status' => 'berhasil',
        'status_code' => 1,
    ];

    $response = $this->postJson('/webhook/payment/ipaymu', $payload);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Unsupported gateway: ipaymu']);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran);

    Event::assertNotDispatched(InvoicePaidEvent::class);
});

test('webhook xendit menolak token salah dengan 401 dan tidak mengubah invoice', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $payload = [
        'id' => 'xnd_inv_wrong_token',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => $trx->total_tagihan,
        'paid_amount' => $trx->total_tagihan,
    ];

    $response = $this->withHeaders(['x-callback-token' => 'token-salah'])
        ->postJson('/webhook/payment/xendit', $payload);

    $response->assertStatus(401);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('webhook xendit menolak request tanpa header token sama sekali dengan 401', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $payload = [
        'id' => 'xnd_inv_no_token',
        'external_id' => $trx->external_id,
        'status' => 'PAID',
        'amount' => $trx->total_tagihan,
        'paid_amount' => $trx->total_tagihan,
    ];

    $response = $this->postJson('/webhook/payment/xendit', $payload);

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

test('webhook xendit menangani event v2 bersarang dan payload tanpa event id tanpa error unique constraint', function () {
    // 1. Kirim event v2 payment_method.activated
    $v2Payload1 = [
        'event' => 'payment_method.activated',
        'business_id' => 'f59fcbb7-848d-4242-af07-a8bb7e3ab37c',
        'created' => '2019-08-24T14:15:22Z',
        'data' => [
            'id' => 'pm-497f6eca-6276-4993-bfeb-53cbbbba6f08',
            'type' => 'EWALLET',
        ],
    ];

    $response1 = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $v2Payload1);

    $response1->assertOk()
        ->assertJson(['status' => 'QUEUED']);

    // 2. Kirim event v2 payout-link.succeeded
    $v2Payload2 = [
        'event' => 'payout-link.succeeded',
        'business_id' => '5f218745736e619164dc8602',
        'created' => '2021-05-07T06:34:47.322Z',
        'data' => [
            'id' => '20b5070c-6c34-4f87-aad4-e6aaed36f199',
            'status' => 'SUCCEEDED',
        ],
    ];

    $response2 = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $v2Payload2);

    $response2->assertOk()
        ->assertJson(['status' => 'QUEUED']);

    // 3. Kirim payload tanpa event id (null) berturut-turut tanpa constraint collision
    $nullIdPayload1 = ['status' => 'PENDING', 'amount' => 1000];
    $nullIdPayload2 = ['status' => 'PENDING', 'amount' => 2000];

    $response3 = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $nullIdPayload1);
    $response3->assertOk();

    $response4 = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $nullIdPayload2);
    $response4->assertOk();
});

test('webhook xendit memproses payload invoice payment v2 resmi dengan benar', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $payload = [
        'id' => '6a924a9143bc9c4d4ed29dbd',
        'fees' => [
            ['type' => 'Biaya Layanan Gateway', 'value' => 4000],
        ],
        'items' => [
            ['name' => 'Paket Internet: Paket sed 90', 'price' => 300000, 'category' => 'Internet', 'quantity' => 1],
        ],
        'amount' => $trx->total_tagihan,
        'status' => 'PAID',
        'created' => '2026-08-29T02:57:21.865Z',
        'is_high' => false,
        'paid_at' => '2026-08-29T02:57:35.381Z',
        'updated' => '2026-08-29T02:57:37.120Z',
        'user_id' => '6a85a31838be35693b6c83dc',
        'currency' => 'IDR',
        'payment_id' => 'qrpy_2e3aac9d-e35b-4cb8-be68-1ae50cda7ee6',
        'description' => 'Tagihan Internet UNMS Invoice '.$this->invoice->no_invoice,
        'external_id' => $trx->external_id,
        'paid_amount' => $trx->total_tagihan,
        'payer_email' => 'salwa29@example.org',
        'merchant_name' => 'Personal',
        'payment_method' => 'QR_CODE',
        'payment_channel' => 'QRIS',
        'payment_details' => [
            'source' => 'DANA',
            'receipt_id' => '1787972255381',
        ],
        'payment_method_id' => 'pm-ab9fe329-e432-4f5b-a57e-34c4d2c16905',
    ];

    $response = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $payload);

    $response->assertOk()
        ->assertJson([
            'message' => 'Webhook received and queued for processing',
            'event_id' => '6a924a9143bc9c4d4ed29dbd',
            'status' => 'QUEUED',
        ]);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->payment_gateway_status)->toBe('PAID');
});

test('webhook xendit memproses format callback payment requests v2 (/v2/payment_requests)', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $v2PaymentRequestPayload = [
        'event' => 'payment.succeeded',
        'business_id' => '6a85a31838be35693b6c83dc',
        'created' => '2026-08-29T02:57:21.865Z',
        'data' => [
            'id' => 'pr-9920102030',
            'reference_id' => $trx->external_id,
            'status' => 'SUCCEEDED',
            'amount' => $trx->total_tagihan,
            'capture_amount' => $trx->total_tagihan,
            'currency' => 'IDR',
            'payment_method' => [
                'type' => 'EWALLET',
                'ewallet' => [
                    'channel_code' => 'SHOPEEPAY',
                    'channel_properties' => [
                        'success_redirect_url' => 'http://localhost:8000/portal/tagihan/64',
                    ],
                ],
            ],
            'payment_id' => 'py-12345678',
        ],
    ];

    $response = $this->withHeaders(['x-callback-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $v2PaymentRequestPayload);

    $response->assertOk()
        ->assertJson([
            'message' => 'Webhook received and queued for processing',
            'event_id' => 'pr-9920102030',
            'status' => 'QUEUED',
        ]);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->payment_gateway_status)->toBe('PAID');
});

test('webhook xendit memproses format callback payment request v3 dengan actions dan virtual account', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $v3Payload = [
        'event' => 'payment_request.succeeded',
        'business_id' => '6a85a31838be35693b6c83dc',
        'api_version' => '2024-05-01',
        'created' => '2026-08-29T03:00:00.000Z',
        'data' => [
            'id' => 'pr-v3-unique-887766',
            'reference_id' => $trx->external_id,
            'status' => 'SUCCEEDED',
            'amount' => $trx->total_tagihan,
            'capture_amount' => $trx->total_tagihan,
            'currency' => 'IDR',
            'payment_method' => [
                'type' => 'VIRTUAL_ACCOUNT',
                'virtual_account' => [
                    'channel_code' => 'BCA',
                    'channel_properties' => [
                        'customer_name' => 'John Doe',
                        'va_number' => '88081234567890',
                    ],
                ],
            ],
            'actions' => [
                [
                    'action' => 'PRESENT_TO_CUSTOMER',
                    'url' => 'https://checkout.xendit.co/web/pr-v3-unique-887766',
                ],
            ],
            'payment_id' => 'py-v3-succ-112233',
        ],
    ];

    $response = $this->withHeaders(['webhook-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $v3Payload);

    $response->assertOk()
        ->assertJson([
            'message' => 'Webhook received and queued for processing',
            'event_id' => 'pr-v3-unique-887766',
            'status' => 'QUEUED',
        ]);

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->payment_gateway_status)->toBe('PAID');
});

test('webhook xendit menangani event payment.failure v3 secara benar', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $v3FailedPayload = [
        'event' => 'payment.failure',
        'business_id' => '6a85a31838be35693b6c83dc',
        'api_version' => '2024-05-01',
        'created' => '2026-08-29T03:05:00.000Z',
        'data' => [
            'id' => 'pr-v3-fail-998877',
            'reference_id' => $trx->external_id,
            'status' => 'FAILED',
            'amount' => $trx->total_tagihan,
            'failure_code' => 'INSUFFICIENT_BALANCE',
        ],
    ];

    $response = $this->withHeaders(['x-webhook-token' => 'test_callback_token'])
        ->postJson('/webhook/payment/xendit', $v3FailedPayload);

    $response->assertOk()
        ->assertJson([
            'status' => 'QUEUED',
        ]);
});

test('webhook xendit dengan token tidak valid tetap membuat WebhookLog beraudit berstatus gagal', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $this->withHeaders(['x-callback-token' => 'token-salah'])
        ->postJson('/webhook/payment/xendit', [
            'id' => 'xnd_audit_reject_test',
            'external_id' => $trx->external_id,
            'status' => 'PAID',
        ])
        ->assertStatus(401);

    $log = WebhookLog::where('event_type', 'webhook.token_rejected')
        ->where('provider', 'xendit')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status_proses)->toBe(StatusWebhookLog::Gagal);
});

test('permintaan webhook payment melebihi batas rate limit menerima 429', function () {
    for ($i = 0; $i < 120; $i++) {
        $this->withHeaders(['x-callback-token' => 'token-salah'])
            ->postJson('/webhook/payment/xendit', ['id' => "rl_payment_test_{$i}"])
            ->assertStatus(401);
    }

    $this->withHeaders(['x-callback-token' => 'token-salah'])
        ->postJson('/webhook/payment/xendit', ['id' => 'rl_payment_test_over'])
        ->assertStatus(429);
});
