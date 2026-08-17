<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Livewire\Invoice\Show;
use App\Livewire\Pembayaran\Index;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use App\Services\Billing\BillingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 200000,
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
        'tanggal_expired' => Carbon::today()->addDays(5),
    ]);

    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 200000,
        'jumlah_setelah_promo' => 200000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

test('user with pembayaran.lihat can view payment history', function () {
    Livewire::actingAs($this->adminUser)->test(Index::class)->assertOk();
});

test('admin can record manual payment and extend active service accumulatively (PRD 4.2 Condition 1)', function () {
    // Current expired is today + 5 days
    $oldExpired = Carbon::today()->addDays(5);
    $this->layanan->update(['tanggal_expired' => $oldExpired]);

    Livewire::actingAs($this->adminUser)
        ->test(Show::class, ['invoice' => $this->invoice])
        ->set('metode', 'manual_admin')
        ->set('jumlah_dibayar', 200000)
        ->set('referensi_transaksi', 'KASIR-001')
        ->set('dibayar_pada', now()->format('Y-m-d\TH:i'))
        ->call('prosesBayar')
        ->assertHasNoErrors();

    // Invoice status becomes Lunas
    expect($this->invoice->fresh()->status)->toBe(StatusInvoice::Lunas)
        ->and($this->invoice->fresh()->metode_pembayaran)->toBe(MetodePembayaran::ManualAdmin);

    // Payment record created
    $pembayaran = Pembayaran::where('invoice_id', $this->invoice->id)->first();
    expect($pembayaran)->not->toBeNull()
        ->and((float) $pembayaran->jumlah_dibayar)->toBe(200000.0)
        ->and($pembayaran->referensi_transaksi)->toBe('KASIR-001');

    // Service expiry extended accumulatively: old_expired + 1 month
    $expectedExpired = $oldExpired->copy()->addMonths(1)->toDateString();
    expect($this->layanan->fresh()->tanggal_expired->toDateString())->toBe($expectedExpired)
        ->and($this->layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('admin recording payment on expired/suspended service resets expiry from payment date (PRD 4.2 Condition 2)', function () {
    // Service is suspended / expired 3 days ago
    $oldExpired = Carbon::today()->subDays(3);
    $this->layanan->update([
        'tanggal_expired' => $oldExpired,
        'status' => StatusLayanan::Suspend,
    ]);

    $billingService = app(BillingService::class);
    $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: [
            'metode' => 'transfer',
            'jumlah_dibayar' => 200000,
            'dibayar_pada' => Carbon::today(),
        ],
        actor: $this->adminUser
    );

    // Expiry resets from today + 1 month
    $expectedExpired = Carbon::today()->addMonths(1)->toDateString();
    expect($this->layanan->fresh()->tanggal_expired->toDateString())->toBe($expectedExpired)
        ->and($this->layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('idempotency guard prevents duplicate payment on already paid invoice', function () {
    $this->invoice->update(['status' => StatusInvoice::Lunas]);

    $billingService = app(BillingService::class);

    expect(fn () => $billingService->prosesPembayaranManual(
        invoice: $this->invoice,
        payload: [
            'metode' => 'manual_admin',
            'jumlah_dibayar' => 200000,
        ],
        actor: $this->adminUser
    ))->toThrow(Exception::class);
});
