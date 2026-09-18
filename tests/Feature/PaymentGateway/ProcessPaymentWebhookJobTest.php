<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Events\InvoicePaidEvent;
use App\Jobs\PaymentGateway\ProcessPaymentWebhookJob;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\PengaturanGateway;
use App\Models\Router;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    $this->pelanggan = Pelanggan::factory()->create([
        'no_reg' => 'BF2908202601',
    ]);

    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create([
        'harga' => 200000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);

    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'router_id' => $router->id,
        'paket_layanan_id' => $paket->id,
        'tanggal_expired' => Carbon::today()->addDays(2)->toDateString(),
        'status' => StatusLayanan::Aktif,
    ]);

    $this->invoice = Invoice::create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'periode_tagihan' => now()->format('Y-m'),
        'jumlah' => 200000,
        'jumlah_setelah_promo' => 200000,
        'tanggal_terbit' => now()->toDateString(),
        'tanggal_jatuh_tempo' => now()->addDays(7)->toDateString(),
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    $this->transaksi = $this->manager->buatPaymentLink($this->invoice, 'xendit');
});

test('ProcessPaymentWebhookJob memproses pelunasan secara asinkron dan memperbarui invoice serta layanan', function () {
    $webhookLog = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_job_test_100',
        'payload' => [
            'id' => 'evt_job_test_100',
            'external_id' => $this->transaksi->external_id,
            'status' => 'PAID',
            'amount' => $this->transaksi->total_tagihan,
            'paid_amount' => $this->transaksi->total_tagihan,
            'payment_method' => 'VIRTUAL_ACCOUNT',
            'payment_channel' => 'BCA',
            'paid_at' => now()->toIso8601String(),
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    $job = new ProcessPaymentWebhookJob($webhookLog->id);
    $job->handle($this->manager);

    $this->invoice->refresh();
    $this->transaksi->refresh();
    $webhookLog->refresh();

    expect($this->invoice->status)->toBe(StatusInvoice::Lunas)
        ->and($this->transaksi->status)->toBe(StatusTransaksiGateway::Paid)
        ->and($webhookLog->status_proses)->toBe(StatusWebhookLog::Diproses)
        ->and(Pembayaran::where('invoice_id', $this->invoice->id)->count())->toBe(1);

    Event::assertDispatched(InvoicePaidEvent::class);
});

test('ProcessPaymentWebhookJob menolak pembayaran jika terjadi anomali nominal underpayment', function () {
    $underpaidAmount = $this->transaksi->total_tagihan - 10000; // Selisih 10.000 lebih murah

    $webhookLog = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_underpaid_101',
        'payload' => [
            'id' => 'evt_underpaid_101',
            'external_id' => $this->transaksi->external_id,
            'status' => 'PAID',
            'amount' => $underpaidAmount,
            'paid_amount' => $underpaidAmount,
            'payment_method' => 'VIRTUAL_ACCOUNT',
            'payment_channel' => 'BCA',
            'paid_at' => now()->toIso8601String(),
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    $job = new ProcessPaymentWebhookJob($webhookLog->id);
    $job->handle($this->manager);

    $this->invoice->refresh();
    $webhookLog->refresh();

    // Invoice TIDAK boleh lunas
    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($webhookLog->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($webhookLog->catatan_error)->toContain('Anomali nominal pembayaran');

    Event::assertNotDispatched(InvoicePaidEvent::class);
});

test('ProcessPaymentWebhookJob menolak pembayaran jika terjadi anomali nominal overpayment', function () {
    $overpaidAmount = $this->transaksi->total_tagihan + 50000; // Kelebihan bayar 50.000

    $webhookLog = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_overpaid_102',
        'payload' => [
            'id' => 'evt_overpaid_102',
            'external_id' => $this->transaksi->external_id,
            'status' => 'PAID',
            'amount' => $overpaidAmount,
            'paid_amount' => $overpaidAmount,
            'payment_method' => 'VIRTUAL_ACCOUNT',
            'payment_channel' => 'BCA',
            'paid_at' => now()->toIso8601String(),
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    $job = new ProcessPaymentWebhookJob($webhookLog->id);
    $job->handle($this->manager);

    $this->invoice->refresh();
    $webhookLog->refresh();

    // Invoice TIDAK boleh lunas -- kelebihan bayar juga ditolak tegas, bukan hanya kekurangan.
    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($webhookLog->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($webhookLog->catatan_error)->toContain('Anomali nominal pembayaran');

    Event::assertNotDispatched(InvoicePaidEvent::class);
});

test('ProcessPaymentWebhookJob menolak callback yang melaporkan paid_amount nol', function () {
    $webhookLog = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_zero_amount_103',
        'payload' => [
            'id' => 'evt_zero_amount_103',
            'external_id' => $this->transaksi->external_id,
            'status' => 'PAID',
            'amount' => 0,
            'paid_amount' => 0,
            'payment_method' => 'VIRTUAL_ACCOUNT',
            'payment_channel' => 'BCA',
            'paid_at' => now()->toIso8601String(),
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    $job = new ProcessPaymentWebhookJob($webhookLog->id);
    $job->handle($this->manager);

    $this->invoice->refresh();
    $webhookLog->refresh();

    // Sebelumnya klausa "$actualAmount > 0" meloloskan paid_amount: 0 begitu saja.
    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($webhookLog->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($webhookLog->catatan_error)->toContain('Anomali nominal pembayaran');

    Event::assertNotDispatched(InvoicePaidEvent::class);
});

test('ProcessPaymentWebhookJob menolak pembayaran dengan mata uang selain IDR', function () {
    $webhookLog = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_foreign_currency_104',
        'payload' => [
            'id' => 'evt_foreign_currency_104',
            'external_id' => $this->transaksi->external_id,
            'status' => 'PAID',
            'amount' => $this->transaksi->total_tagihan,
            'paid_amount' => $this->transaksi->total_tagihan,
            'currency' => 'USD',
            'payment_method' => 'VIRTUAL_ACCOUNT',
            'payment_channel' => 'BCA',
            'paid_at' => now()->toIso8601String(),
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    $job = new ProcessPaymentWebhookJob($webhookLog->id);
    $job->handle($this->manager);

    $this->invoice->refresh();
    $webhookLog->refresh();

    expect($this->invoice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and($webhookLog->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($webhookLog->catatan_error)->toContain('Anomali mata uang pembayaran');

    Event::assertNotDispatched(InvoicePaidEvent::class);
});

test('ProcessPaymentWebhookJob::failed menandai webhook log Gagal dengan pesan exception setelah retry habis', function () {
    $webhookLog = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_exhausted_105',
        'payload' => ['id' => 'evt_exhausted_105'],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    $job = new ProcessPaymentWebhookJob($webhookLog->id);
    $job->failed(new Exception('Simulasi koneksi Xendit gagal total setelah 3x percobaan'));

    $webhookLog->refresh();
    expect($webhookLog->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($webhookLog->catatan_error)->toContain('Simulasi koneksi Xendit gagal total setelah 3x percobaan');
});

test('ProcessPaymentWebhookJob menjamin idempotensi jika job dieksekusi berkali-kali', function () {
    $webhookLog = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_idempotent_102',
        'payload' => [
            'id' => 'evt_idempotent_102',
            'external_id' => $this->transaksi->external_id,
            'status' => 'PAID',
            'amount' => $this->transaksi->total_tagihan,
            'paid_amount' => $this->transaksi->total_tagihan,
            'payment_method' => 'VIRTUAL_ACCOUNT',
            'payment_channel' => 'BCA',
            'paid_at' => now()->toIso8601String(),
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    $job = new ProcessPaymentWebhookJob($webhookLog->id);
    // Eksekusi pertama
    $job->handle($this->manager);

    $expiredFirst = $this->layanan->fresh()->tanggal_expired;
    $pembayaranCountFirst = Pembayaran::where('invoice_id', $this->invoice->id)->count();

    // Eksekusi kedua (simulasi duplicate queue execution)
    $job->handle($this->manager);

    $expiredSecond = $this->layanan->fresh()->tanggal_expired;
    $pembayaranCountSecond = Pembayaran::where('invoice_id', $this->invoice->id)->count();

    expect($pembayaranCountFirst)->toBe(1)
        ->and($pembayaranCountSecond)->toBe(1)
        ->and($expiredFirst->toDateString())->toBe($expiredSecond->toDateString());
});

test('database unique constraint mencegah duplikasi webhook_log untuk provider dan event_id yang sama', function () {
    WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_unique_xendit_103',
        'payload' => ['test' => true],
        'status_proses' => StatusWebhookLog::Diproses,
        'diterima_pada' => now(),
    ]);

    expect(fn () => WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_unique_xendit_103',
        'payload' => ['test' => true],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]))->toThrow(QueryException::class);
});

test('database unique constraint mencegah duplikasi pembayaran untuk metode dan referensi yang sama', function () {
    Pembayaran::create([
        'invoice_id' => $this->invoice->id,
        'metode' => MetodePembayaran::PaymentGateway,
        'referensi_transaksi' => 'REF_UNIQUE_TRX_104',
        'jumlah_dibayar' => 200000,
        'dibayar_pada' => now(),
    ]);

    expect(fn () => Pembayaran::create([
        'invoice_id' => $this->invoice->id,
        'metode' => MetodePembayaran::PaymentGateway,
        'referensi_transaksi' => 'REF_UNIQUE_TRX_104',
        'jumlah_dibayar' => 200000,
        'dibayar_pada' => now(),
    ]))->toThrow(QueryException::class);
});

test('ProcessPaymentWebhookJob tidak melunasi invoice yang sudah digabung dan menandainya untuk penanganan manual', function () {
    $this->invoice->update(['status' => StatusInvoice::Digabung]);

    $webhookLog = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_digabung_105',
        'payload' => [
            'id' => 'evt_digabung_105',
            'external_id' => $this->transaksi->external_id,
            'status' => 'PAID',
            'amount' => $this->transaksi->total_tagihan,
            'paid_amount' => $this->transaksi->total_tagihan,
            'currency' => 'IDR',
            'payment_method' => 'VIRTUAL_ACCOUNT',
            'payment_channel' => 'BCA',
            'paid_at' => now()->toIso8601String(),
        ],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now(),
    ]);

    (new ProcessPaymentWebhookJob($webhookLog->id))->handle($this->manager);

    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Digabung)
        ->and($webhookLog->fresh()->status_proses)->toBe(StatusWebhookLog::Gagal)
        ->and($webhookLog->fresh()->catatan_error)->toContain('sudah digabung');

    Event::assertNotDispatched(InvoicePaidEvent::class);
});
