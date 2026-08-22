<?php

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Enums\StatusRouter;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Livewire\Dashboard as DashboardComponent;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Router;
use App\Models\Ticket;
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

test('pengguna terotentikasi dapat melihat dashboard operasional dan metrik', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Operasional & Billing')
        ->assertSee('Pendapatan Terkumpul')
        ->assertSee('Pelanggan Aktif')
        ->assertSee('Tagihan Menunggu')
        ->assertSee('Tiket Terbuka & Jaringan');
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

test('komponen dashboard menghitung kpi dan data grafik secara akurat', function () {
    $pelanggan = Pelanggan::factory()->create(['status' => StatusPelanggan::Aktif]);
    $router = Router::factory()->create(['status_koneksi' => StatusRouter::Online]);
    $paket = PaketLayanan::factory()->create(['nama_paket' => 'Paket Ultra 50M']);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
    ]);

    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'layanan_pelanggan_id' => $layanan->id,
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'status' => StatusInvoice::Lunas,
    ]);

    Pembayaran::factory()->create([
        'invoice_id' => $invoice->id,
        'jumlah_dibayar' => 300000,
        'metode' => MetodePembayaran::ManualAdmin,
        'dibayar_pada' => now(),
    ]);

    Ticket::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'status' => StatusTicket::Baru,
        'prioritas' => PrioritasTicket::Tinggi,
    ]);

    Livewire::actingAs($this->user)
        ->test(DashboardComponent::class)
        ->assertOk()
        ->assertSee('300.000')
        ->assertSee('Paket Ultra 50M');
});
