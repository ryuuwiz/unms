<?php

use App\Enums\StatusLayanan;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Promo;
use App\Models\Router;
use App\Services\Billing\BillingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();

    $this->billing = app(BillingService::class);
    $this->router = Router::factory()->create();
    $this->layanan = LayananPelanggan::factory()->create([
        'router_id' => $this->router->id,
        'paket_layanan_id' => PaketLayanan::factory()->create(['harga' => 250000]),
        'status' => StatusLayanan::Aktif,
    ]);
});

test('invoice periodik tanpa IP publik tidak berubah', function () {
    $invoice = $this->billing->generateInvoice($this->layanan, periodeTagihan: '2026-09');

    expect((float) $invoice->jumlah)->toBe(250000.0)
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(250000.0)
        ->and($invoice->keterangan)->toBeNull();
});

test('invoice periodik menagih harga paket ditambah IP publik dengan rincian di keterangan', function () {
    IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => $this->layanan->id, 'alamat_ip' => '203.0.113.10', 'harga_bulanan' => 50000, 'harga_ditagih' => 50000]);

    $invoice = $this->billing->generateInvoice($this->layanan->fresh(), periodeTagihan: '2026-09');

    expect((float) $invoice->jumlah)->toBe(300000.0)
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(300000.0)
        ->and($invoice->keterangan)->toBe('Paket: Rp 250.000; IP Publik 203.0.113.10: Rp 50.000');
});

test('harga yang ditagih memakai snapshot, bukan harga daftar terbaru', function () {
    IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => $this->layanan->id, 'harga_bulanan' => 99000, 'harga_ditagih' => 50000]);

    $invoice = $this->billing->generateInvoice($this->layanan->fresh(), periodeTagihan: '2026-09');

    expect((float) $invoice->jumlah)->toBe(300000.0);
});

test('diskon promo hanya berlaku pada harga paket, tidak pada IP publik', function () {
    IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => $this->layanan->id, 'harga_bulanan' => 50000, 'harga_ditagih' => 50000]);
    $promo = Promo::factory()->create(['diskon_nilai' => 25000, 'minimal_nominal_invoice' => 100000]);

    $invoice = $this->billing->generateInvoice($this->layanan->fresh(), promo: $promo, periodeTagihan: '2026-09');

    expect((float) $invoice->jumlah)->toBe(300000.0)
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(275000.0);
});

test('invoice manual tetap ad-hoc tanpa periode dan tidak menambahkan IP publik', function () {
    IpPublik::factory()->create(['router_id' => $this->router->id, 'layanan_pelanggan_id' => $this->layanan->id, 'harga_ditagih' => 50000]);

    $invoice = $this->billing->generateManualInvoice($this->layanan->fresh(), 120000, 'Biaya pasang IP publik');

    expect((float) $invoice->jumlah)->toBe(120000.0)
        ->and($invoice->periode_tagihan)->toBeNull();
});
