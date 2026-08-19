<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusTransaksiGateway;
use App\Enums\UserStatus;
use App\Livewire\Pembayaran\TransaksiGateway\Index;
use App\Livewire\Pembayaran\TransaksiGateway\Show;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\TransaksiPaymentGateway;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $this->router = Router::factory()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);

    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 200000,
        'jumlah_setelah_promo' => 200000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    $this->transaksi = TransaksiPaymentGateway::create([
        'invoice_id' => $this->invoice->id,
        'gateway' => 'xendit',
        'external_id' => 'INV-TEST-VA-001',
        'channel' => GatewayChannel::VirtualAccount,
        'channel_detail' => 'bca',
        'nomor_pembayaran' => '880812345678',
        'total_tagihan' => 200000,
        'fee_gateway' => 0,
        'status' => StatusTransaksiGateway::Pending,
        'expired_at' => now()->addDays(3),
    ]);
});

test('admin dapat melihat daftar transaksi payment gateway', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertOk()
        ->assertSee($this->transaksi->external_id)
        ->assertSee('880812345678');
});

test('admin dapat melihat rincian transaksi gateway', function () {
    Livewire::actingAs($this->admin)
        ->test(Show::class, ['transaksi' => $this->transaksi])
        ->assertOk()
        ->assertSee($this->transaksi->external_id)
        ->assertSee($this->invoice->no_invoice);
});
