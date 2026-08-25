<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Livewire\Dashboard as DashboardComponent;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\PerusahaanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        PerusahaanSeeder::class,
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
});

test('tamu diarahkan ke halaman login saat mengakses dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('pengguna terotentikasi dapat melihat dashboard operasional dan metrik baru', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Operasional & Billing')
        ->assertSee('Total Pelanggan')
        ->assertSee('Pendapatan Hari Ini')
        ->assertSee('Pendapatan Bulan Ini')
        ->assertSee('Tagihan Bulan Ini')
        ->assertSee('Tren Pendapatan & Transaksi Harian', false)
        ->assertSee('Transaksi Pembayaran Terbaru')
        ->assertSee('Pelanggan Expired & Jatuh Tempo', false);
});

test('komponen dashboard dapat memfilter periode waktu', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertOk()
        ->assertSet('period', 'this_month')
        ->call('setPeriod', 'last_30_days')
        ->assertSet('period', 'last_30_days')
        ->call('setPeriod', 'this_year')
        ->assertSet('period', 'this_year')
        ->call('setPeriod', 'all')
        ->assertSet('period', 'all');
});

test('komponen dashboard menghitung kpi, tren harian, dan expired services secara akurat', function () {
    $pelangganAktif = Pelanggan::factory()->create([
        'nama_depan' => 'Budi',
        'nama_belakang' => 'Santoso',
        'status' => StatusPelanggan::Aktif,
    ]);
    $pelangganNonaktif = Pelanggan::factory()->create([
        'nama_depan' => 'Joko',
        'nama_belakang' => 'Susilo',
        'status' => StatusPelanggan::Off,
    ]);

    $router = Router::factory()->create();
    $paket = PaketLayanan::factory()->create(['nama_paket' => 'Paket Ultra 50M']);

    // Layanan aktif & expired kemarin
    $layananExpired = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelangganAktif->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
        'tanggal_expired' => now()->subDay()->toDateString(),
    ]);

    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $pelangganAktif->id,
        'layanan_pelanggan_id' => $layananExpired->id,
        'jumlah' => 350000,
        'jumlah_setelah_promo' => 350000,
        'tanggal_terbit' => now(),
        'status' => StatusInvoice::Lunas,
    ]);

    Pembayaran::factory()->create([
        'invoice_id' => $invoice->id,
        'jumlah_dibayar' => 350000,
        'metode' => MetodePembayaran::ManualAdmin,
        'dibayar_pada' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertOk()
        ->assertSee('350.000')
        ->assertSee('Paket Ultra 50M')
        ->assertSee('Budi Santoso')
        ->assertSee('Expired');
});
