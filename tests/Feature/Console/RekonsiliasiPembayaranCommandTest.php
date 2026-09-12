<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusTransaksiGateway;
use App\Enums\StatusWebhookLog;
use App\Jobs\PaymentGateway\ProcessPaymentWebhookJob;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\Router;
use App\Models\TransaksiPaymentGateway;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

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
            'callback_token' => 'test_callback_token',
        ],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ]);

    $this->pelanggan = Pelanggan::factory()->create(['no_reg' => 'BF3009202601']);
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
        'tanggal_expired' => now()->toDateString(),
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
});

test('arah A: dispatch ulang WebhookLog yang mandek di status diterima melewati ambang waktu', function () {
    Queue::fake();

    $mandek = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_mandek_1',
        'payload' => ['id' => 'evt_mandek_1'],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now()->subMinutes(10), // melewati ambang 5 menit
    ]);

    $baruSaja = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_baru_1',
        'payload' => ['id' => 'evt_baru_1'],
        'status_proses' => StatusWebhookLog::Diterima,
        'diterima_pada' => now()->subMinute(), // masih dalam ambang, belum boleh disweep
    ]);

    $gagal = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_gagal_1',
        'payload' => ['id' => 'evt_gagal_1'],
        'status_proses' => StatusWebhookLog::Gagal,
        'diterima_pada' => now()->subMinute(),
    ]);

    $sudahDiproses = WebhookLog::create([
        'provider' => 'xendit',
        'event_type' => 'payment.xendit',
        'provider_event_id' => 'evt_sukses_1',
        'payload' => ['id' => 'evt_sukses_1'],
        'status_proses' => StatusWebhookLog::Diproses,
        'diterima_pada' => now()->subMinutes(10),
    ]);

    $this->artisan('pembayaran:rekonsiliasi')->assertSuccessful();

    Queue::assertPushed(ProcessPaymentWebhookJob::class, function ($job) use ($mandek) {
        return $job->webhookLogId === $mandek->id;
    });
    Queue::assertPushed(ProcessPaymentWebhookJob::class, function ($job) use ($gagal) {
        return $job->webhookLogId === $gagal->id;
    });
    Queue::assertNotPushed(ProcessPaymentWebhookJob::class, function ($job) use ($baruSaja) {
        return $job->webhookLogId === $baruSaja->id;
    });
    Queue::assertNotPushed(ProcessPaymentWebhookJob::class, function ($job) use ($sudahDiproses) {
        return $job->webhookLogId === $sudahDiproses->id;
    });
});

test('arah B: transaksi pending yang ternyata sudah PAID di gateway ikut dilunasi oleh sweeper', function () {
    // Driver palsu yang selalu melaporkan PAID ke checkStatus(), mensimulasikan webhook
    // yang hilang tapi pembayaran sebenarnya sudah sukses di sisi Xendit.
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');

    $paidDriver = new class extends XenditDriver
    {
        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            $amount = $target instanceof TransaksiPaymentGateway ? (float) $target->total_tagihan : 0.0;

            return [
                'id' => 'xnd_reconciled_evt_1',
                'status' => 'PAID',
                'paid_amount' => $amount,
                'amount' => $amount,
            ];
        }
    };
    $this->manager->registerDriver('xendit', $paidDriver);
    // PaymentGatewayManager bukan singleton -- ikat instance yang sudah dikonfigurasi
    // dengan driver palsu ini agar command mengambil instance yang sama, bukan yang baru.
    $this->app->instance(PaymentGatewayManager::class, $this->manager);

    $this->artisan('pembayaran:rekonsiliasi')->assertSuccessful();

    $this->invoice->refresh();
    expect($this->invoice->status)->toBe(StatusInvoice::Lunas);

    $trx->refresh();
    expect($trx->status)->toBe(StatusTransaksiGateway::Paid);
});

test('transaksi pending yang invoicenya sudah lunas tidak lagi disentuh sweeper', function () {
    $trx = $this->manager->buatPaymentLink($this->invoice, 'xendit');
    $this->invoice->update(['status' => StatusInvoice::Lunas]);

    // Driver yang akan melempar exception jika checkStatus() dipanggil -- membuktikan
    // sweeper tidak lagi memproses transaksi milik invoice yang sudah lunas.
    $shouldNotBeCalledDriver = new class extends XenditDriver
    {
        public function checkStatus(Invoice|TransaksiPaymentGateway $target, PengaturanGateway $setting): array
        {
            throw new Exception('checkStatus tidak seharusnya dipanggil untuk invoice yang sudah lunas.');
        }
    };
    $this->manager->registerDriver('xendit', $shouldNotBeCalledDriver);
    $this->app->instance(PaymentGatewayManager::class, $this->manager);

    $this->artisan('pembayaran:rekonsiliasi')->assertSuccessful();

    $trx->refresh();
    expect($trx->status)->toBe(StatusTransaksiGateway::Pending);
});
