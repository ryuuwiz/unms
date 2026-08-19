<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['services.xendit.callback_token' => 'test_xendit_token_xyz']);

    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 250000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $this->router = Router::factory()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => Carbon::today()->addDays(5)->toDateString(),
    ]);

    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 250000,
        'jumlah_setelah_promo' => 250000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    $this->externalId = "INV-{$this->invoice->no_invoice}-VA-BCA-123456";

    $this->transaksi = TransaksiPaymentGateway::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'xendit',
        'external_id' => $this->externalId,
        'xendit_reference_id' => 'pr_test_123',
        'channel' => GatewayChannel::VirtualAccount,
        'channel_detail' => 'bca',
        'nomor_pembayaran' => '880812345678',
        'total_tagihan' => 250000,
        'fee_gateway' => 0,
        'status' => StatusTransaksiGateway::Pending,
        'expired_at' => Carbon::now()->addDays(3),
    ]);
});

test('webhook menolak request jika token tidak valid dengan HTTP 401', function () {
    $response = $this->postJson('/webhook/xendit', [
        'id' => 'evt_123',
    ], [
        'x-callback-token' => 'wrong_token',
    ]);

    $response->assertStatus(401);
    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::MenungguPembayaran);
});

test('webhook memproses callback valid, melunaskan invoice, dan memperpanjang masa aktif', function () {
    Event::fake([InvoicePaidEvent::class]);

    $payload = [
        'event' => 'payment.succeeded',
        'id' => 'evt_valid_123',
        'data' => [
            'id' => 'pr_test_123',
            'reference_id' => $this->externalId,
            'status' => 'SUCCEEDED',
            'amount' => 250000,
            'payment_method' => [
                'type' => 'VIRTUAL_ACCOUNT',
                'virtual_account' => [
                    'channel_code' => 'BCA',
                    'channel_properties' => [
                        'account_number' => '880812345678',
                    ],
                ],
            ],
            'updated' => now()->toIso8601String(),
        ],
    ];

    $response = $this->postJson('/webhook/xendit', $payload, [
        'x-callback-token' => 'test_xendit_token_xyz',
    ]);

    $response->assertStatus(200);

    // 1. Cek status invoice & transaksi
    expect($this->invoice->fresh()->isLunas())->toBeTrue()
        ->and($this->transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Paid);

    // 2. Cek record pembayaran
    expect(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(1);

    // 3. Cek perpanjangan masa aktif (5 hari + 1 bulan = ~35/36 hari)
    $expiredBaru = Carbon::parse($this->layanan->fresh()->tanggal_expired);
    expect($expiredBaru->greaterThan(Carbon::today()->addDays(25)))->toBeTrue();

    // 4. Cek audit webhook log
    expect(WebhookLog::where('xendit_event_id', 'evt_valid_123')->first()->status_proses)->toBe(StatusWebhookLog::Diproses);

    // 5. Cek Event dipancarkan
    Event::assertDispatched(InvoicePaidEvent::class);
});

test('webhook bersifat idempoten terhadap retry event ID yang sama', function () {
    WebhookLog::create([
        'event_type' => 'payment.succeeded',
        'xendit_event_id' => 'evt_duplicate_999',
        'payload' => [],
        'status_proses' => StatusWebhookLog::Diproses,
        'diterima_pada' => now(),
    ]);

    $payload = [
        'event' => 'payment.succeeded',
        'id' => 'evt_duplicate_999',
        'data' => [
            'reference_id' => $this->externalId,
            'amount' => 250000,
        ],
    ];

    $response = $this->postJson('/webhook/xendit', $payload, [
        'x-callback-token' => 'test_xendit_token_xyz',
    ]);

    $response->assertStatus(200);
    $response->assertJsonFragment(['message' => 'Webhook already processed']);

    // Pastikan tidak ada pembayaran duplikat yang dibuat
    expect(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(0);
});

test('webhook aman terhadap invoice yang sudah lunas sebelumnya', function () {
    $this->invoice->update([
        'status' => StatusInvoice::Lunas,
        'tanggal_lunas' => now()->toDateString(),
    ]);

    $payload = [
        'event' => 'payment.succeeded',
        'id' => 'evt_already_paid_123',
        'data' => [
            'reference_id' => $this->externalId,
            'amount' => 250000,
        ],
    ];

    $response = $this->postJson('/webhook/xendit', $payload, [
        'x-callback-token' => 'test_xendit_token_xyz',
    ]);

    $response->assertStatus(200);
    expect(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(0);
});
