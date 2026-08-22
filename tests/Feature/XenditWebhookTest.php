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
        'xendit_invoice_id' => 'inv_xendit_test_123',
        'xendit_invoice_url' => 'https://checkout-staging.xendit.co/v2/inv_xendit_test_123',
        'xendit_status' => 'PENDING',
        'xendit_expired_at' => Carbon::now()->addDays(3),
    ]);

    $this->externalId = "INV-{$this->invoice->no_invoice}-1755848800";

    $this->transaksi = TransaksiPaymentGateway::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'xendit',
        'external_id' => $this->externalId,
        'xendit_reference_id' => 'inv_xendit_test_123',
        'channel' => GatewayChannel::Invoice,
        'channel_detail' => 'hosted_invoice',
        'nomor_pembayaran' => 'https://checkout-staging.xendit.co/v2/inv_xendit_test_123',
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

test('webhook menolak request tanpa header token dengan HTTP 401', function () {
    $response = $this->postJson('/webhook/xendit', [
        'id' => 'evt_123',
    ]);

    $response->assertStatus(401);
});

test('webhook memproses callback format Xendit Hosted Invoice PAID, melunaskan invoice, dan memperpanjang masa aktif', function () {
    Event::fake([InvoicePaidEvent::class]);

    $payload = [
        'id' => 'inv_xendit_test_123',
        'external_id' => $this->externalId,
        'user_id' => 'user_123',
        'status' => 'PAID',
        'merchant_name' => 'UNMS ISP',
        'amount' => 250000,
        'paid_amount' => 250000,
        'payer_email' => $this->pelanggan->email,
        'description' => "Tagihan Internet UNMS {$this->invoice->no_invoice}",
        'payment_method' => 'BANK_TRANSFER',
        'payment_channel' => 'BCA',
        'payment_destination' => '880812345678',
        'paid_at' => now()->toIso8601String(),
    ];

    $response = $this->postJson('/webhook/xendit', $payload, [
        'x-callback-token' => 'test_xendit_token_xyz',
    ]);

    $response->assertStatus(200);

    // 1. Cek status invoice & transaksi
    expect($this->invoice->fresh()->isLunas())->toBeTrue()
        ->and($this->invoice->fresh()->xendit_status)->toBe('PAID')
        ->and($this->transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Paid);

    // 2. Cek record pembayaran
    expect(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(1);

    // 3. Cek perpanjangan masa aktif (5 hari + 1 bulan = ~35/36 hari)
    $expiredBaru = Carbon::parse($this->layanan->fresh()->tanggal_expired);
    expect($expiredBaru->greaterThan(Carbon::today()->addDays(25)))->toBeTrue();

    // 4. Cek audit webhook log
    expect(WebhookLog::where('xendit_event_id', 'inv_xendit_test_123')->first()->status_proses)->toBe(StatusWebhookLog::Diproses);

    // 5. Cek Event dipancarkan
    Event::assertDispatched(InvoicePaidEvent::class);
});

test('webhook memproses callback EXPIRED dengan me-reset link invoice lokal dan menandai transaksi expired', function () {
    $payload = [
        'id' => 'inv_xendit_test_123',
        'external_id' => $this->externalId,
        'status' => 'EXPIRED',
        'amount' => 250000,
        'payer_email' => $this->pelanggan->email,
    ];

    $response = $this->postJson('/webhook/xendit', $payload, [
        'x-callback-token' => 'test_xendit_token_xyz',
    ]);

    $response->assertStatus(200);

    // Invoice tetap menunggu_pembayaran tapi xendit_invoice_url di-reset menjadi null
    $freshInvoice = $this->invoice->fresh();
    expect($freshInvoice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($freshInvoice->xendit_invoice_url)->toBeNull()
        ->and($freshInvoice->xendit_status)->toBe('EXPIRED')
        ->and($this->transaksi->fresh()->status)->toBe(StatusTransaksiGateway::Expired);
});

test('webhook bersifat idempoten terhadap retry event ID yang sama', function () {
    WebhookLog::create([
        'event_type' => 'invoice.paid',
        'xendit_event_id' => 'inv_duplicate_999',
        'payload' => [],
        'status_proses' => StatusWebhookLog::Diproses,
        'diterima_pada' => now(),
    ]);

    $payload = [
        'id' => 'inv_duplicate_999',
        'external_id' => $this->externalId,
        'status' => 'PAID',
        'amount' => 250000,
        'paid_amount' => 250000,
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
        'id' => 'inv_already_paid_123',
        'external_id' => $this->externalId,
        'status' => 'PAID',
        'amount' => 250000,
        'paid_amount' => 250000,
    ];

    $response = $this->postJson('/webhook/xendit', $payload, [
        'x-callback-token' => 'test_xendit_token_xyz',
    ]);

    $response->assertStatus(200);
    expect(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(0);
});
