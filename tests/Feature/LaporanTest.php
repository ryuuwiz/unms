<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Livewire\Laporan\Billing;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
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
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id, 'harga' => 200000]);
    $this->router = Router::factory()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);
});

test('user with laporan.lihat can view billing report summary', function () {
    Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 200000,
        'jumlah_setelah_promo' => 200000,
        'status' => StatusInvoice::Lunas,
        'tanggal_terbit' => Carbon::today(),
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Billing::class)
        ->assertOk()
        ->assertViewHas('totalLunas', fn ($val) => $val === 200000.0);
});

test('scheduled command invoice:generate creates invoices for services expiring within 7 days', function () {
    // Tanggal dipatok (bukan Carbon::today()->addDays()) supaya hasil tidak bergantung
    // pada hari suite ini dijalankan -- lihat pola yang sama di InvoiceTest.php.
    $this->travelTo(Carbon::create(2026, 9, 24));
    $this->layanan->update([
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => '2026-10-10',
    ]);

    $this->artisan('invoice:generate')
        ->assertSuccessful();

    $invoice = Invoice::where('layanan_pelanggan_id', $this->layanan->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(StatusInvoice::MenungguPembayaran)
        ->and((float) $invoice->jumlah)->toBe(200000.0);
});

test('scheduled command invoice:cek-kadaluarsa marks overdue unpaid invoices as kadaluarsa', function () {
    $overdueInvoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'status' => StatusInvoice::MenungguPembayaran,
        'tanggal_terbit' => Carbon::today()->subDays(10),
        'tanggal_jatuh_tempo' => Carbon::today()->subDays(3), // overdue
    ]);

    $this->artisan('invoice:cek-kadaluarsa')
        ->assertSuccessful();

    expect($overdueInvoice->fresh()->status)->toBe(StatusInvoice::Kadaluarsa);
});
