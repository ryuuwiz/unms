<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusTransaksiGateway;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\PengaturanGateway;
use App\Models\Router;
use App\Models\TransaksiPaymentGateway;
use App\Services\PaymentGateway\Drivers\XenditDriver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Xendit\Invoice\Invoice as XenditInvoice;
use Xendit\Invoice\InvoiceApi;

uses(RefreshDatabase::class);

// ADR 0039: mock Xendit responses (tanpa panggilan API sama sekali) HANYA boleh terjadi di
// APP_ENV='testing' -- satu-satunya nilai yang tidak pernah bisa muncul di server sungguhan.
// 'local' sengaja TIDAK termasuk lagi: developer dengan key test Xendit asli berhak mendapat
// panggilan API sungguhan, dan APP_ENV yang salah konfigurasi jadi 'local' di produksi tidak
// lagi bisa memicu URL palsu.
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->driver = new XenditDriver;

    $this->pelanggan = Pelanggan::factory()->create();
    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create(['harga' => 250000]);
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
    ]);
    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 250000,
        'jumlah_setelah_promo' => 250000,
    ]);
});

test('APP_ENV testing selalu menghasilkan link mock, terlepas dari sandbox_mode', function () {
    // Test suite selalu berjalan dengan APP_ENV=testing (phpunit.xml) -- ini alur normalnya.
    $setting = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Utama',
        'credentials' => ['secret_key' => 'xnd_development_test_123'],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => false, // sengaja false: sandbox_mode tidak lagi relevan untuk mock
    ]);

    $response = $this->driver->createPaymentLink($this->invoice, $setting);

    expect($response->paymentUrl)->toContain('checkout-staging.xendit.co')
        ->and($response->rawResponse['mock'] ?? null)->toBeTrue();
});

test('APP_ENV local TIDAK lagi diam-diam membuat link mock', function () {
    // Simulasikan seolah APP_ENV terbaca 'local' (skenario persis yang membuat link palsu
    // bocor ke produksi -- lihat ADR 0039). Key dikosongkan agar percobaan panggilan API
    // sungguhan gagal cepat & tanpa jaringan (bukti bahwa jalur mock benar-benar tertutup,
    // bukan cuma kebetulan lolos lewat jalur lain).
    app()->detectEnvironment(fn () => 'local');
    config(['services.xendit.secret_key' => '']);

    $setting = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Utama (Live)',
        'credentials' => [],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => true, // sengaja true: sandbox_mode juga tidak boleh lagi memicu mock
    ]);

    expect(fn () => $this->driver->createPaymentLink($this->invoice, $setting))
        ->toThrow(Exception::class, 'Xendit Secret Key belum dikonfigurasi');
});

test('invoice dengan link mock lama yang bocor sebelum perbaikan dianggap tidak aktif di luar testing', function () {
    // Skenario persis laporan pelanggan BF1409202601: invoice sudah menyimpan URL dari bug
    // lama SEBELUM perbaikan ini ada (payment_gateway_expired_at belum lewat, jadi tanpa
    // pengecekan 'inv_mock_' link ini akan dianggap "masih aktif" selamanya dan tidak pernah
    // diregenerasi).
    $this->invoice->update([
        'payment_gateway_url' => 'https://checkout-staging.xendit.co/v2/inv_mock_leaked123',
        'payment_gateway_id' => 'inv_mock_leaked123',
        'payment_gateway_status' => 'PENDING',
        'payment_gateway_expired_at' => now()->addDay(),
    ]);

    app()->detectEnvironment(fn () => 'local');
    expect($this->invoice->hasActivePaymentLink())->toBeFalse();
});

test('invoice dengan link mock di APP_ENV testing tetap dianggap aktif (dipakai test lain)', function () {
    // Di 'testing', 'inv_mock_' SELALU sengaja dibuat -- test lain (mis. PaymentGatewayManagerTest)
    // bergantung pada link ini berperilaku seperti link normal, bukan otomatis "tidak aktif".
    $this->invoice->update([
        'payment_gateway_url' => 'https://checkout-staging.xendit.co/v2/inv_mock_leaked123',
        'payment_gateway_id' => 'inv_mock_leaked123',
        'payment_gateway_status' => 'PENDING',
        'payment_gateway_expired_at' => now()->addDay(),
    ]);

    expect($this->invoice->hasActivePaymentLink())->toBeTrue();
});

test('checkStatus di APP_ENV local tidak melaporkan PENDING palsu', function () {
    app()->detectEnvironment(fn () => 'local');
    config(['services.xendit.secret_key' => '']);

    $setting = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Utama (Live)',
        'credentials' => [],
        'is_default' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ]);

    $this->invoice->update(['payment_gateway_id' => 'inv_real_but_unreachable_without_key']);

    $result = $this->driver->checkStatus($this->invoice, $setting);

    // Tanpa API key nyata, harus melaporkan error eksplisit -- bukan status PENDING palsu
    // yang menutupi kondisi sebenarnya (lihat ADR 0039).
    expect($result)->toHaveKey('error')
        ->and($result['status'] ?? null)->not->toBe('PENDING');
});

test('checkStatus memulihkan invoice melalui external_id saat id lama tidak ditemukan', function () {
    app()->detectEnvironment(fn () => 'local');

    $setting = PengaturanGateway::create([
        'provider' => 'xendit',
        'gateway' => 'xendit',
        'nama' => 'Xendit Utama',
        'credentials' => ['secret_key' => 'xnd_development_test_123'],
        'is_default' => true,
        'is_active' => true,
    ]);
    $transaksi = TransaksiPaymentGateway::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'xendit',
        'external_id' => 'INV-RECOVERY-001',
        'xendit_reference_id' => 'old_id',
        'channel' => GatewayChannel::Invoice,
        'total_tagihan' => 254000,
        'fee_gateway' => 4000,
        'status' => StatusTransaksiGateway::Pending,
    ]);

    $remoteInvoice = (new XenditInvoice)
        ->setId('6abe1a0cad9cd3582f98b526')
        ->setExternalId('INV-RECOVERY-001')
        ->setStatus('PAID')
        ->setAmount(254000)
        ->setInvoiceUrl('https://checkout.xendit.co/web/6abe1a0cad9cd3582f98b526');
    $invoiceApi = Mockery::mock(InvoiceApi::class);
    $invoiceApi->shouldReceive('getInvoiceById')->once()->andThrow(new Exception('Could not find invoice by id old_id'));
    $invoiceApi->shouldReceive('getInvoices')->once()->andReturn([$remoteInvoice]);

    $driver = new class($invoiceApi) extends XenditDriver
    {
        public function __construct(private InvoiceApi $invoiceApi) {}

        protected function getInvoiceApi(string $apiKey): InvoiceApi
        {
            return $this->invoiceApi;
        }
    };

    $result = $driver->checkStatus($transaksi, $setting);

    expect($result['id'])->toBe('6abe1a0cad9cd3582f98b526')
        ->and($result['recovered_by'])->toBe('external_id')
        ->and($result['status'])->toBe('PAID');
});
