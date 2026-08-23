<?php

use App\Enums\GatewayChannel;
use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Livewire\Portal\Invoice\Index;
use App\Livewire\Portal\Invoice\Show;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 300000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $this->router = Router::factory()->create();

    $this->pelanggan = Pelanggan::factory()->create(['email' => 'client1@test.com']);
    $this->akun = $this->pelanggan->akunPelanggan;
    $this->akun->update(['password' => Hash::make('password123')]);

    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
    ]);

    $this->invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 300000,
        'jumlah_setelah_promo' => 300000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

test('pelanggan dapat melihat daftar tagihannya di portal', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Index::class)
        ->assertOk()
        ->assertSee($this->invoice->no_invoice);
});

test('pelanggan dapat melihat rincian tagihan miliknya', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->assertOk()
        ->assertSee($this->invoice->no_invoice);
});

test('pelanggan tidak dapat melihat tagihan milik pelanggan lain (403)', function () {
    $pelangganLain = Pelanggan::factory()->create(['email' => 'other@test.com']);
    $invoiceLain = Invoice::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'jumlah' => 100000,
        'jumlah_setelah_promo' => 100000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $invoiceLain])
        ->assertForbidden();
});

test('pelanggan dapat menekan tombol bayar dan di-redirect ke xendit_invoice_url', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar')
        ->assertRedirect();

    $this->invoice->refresh();
    expect($this->invoice->xendit_invoice_url)->not->toBeNull()
        ->and($this->invoice->xendit_invoice_id)->not->toBeNull()
        ->and($this->invoice->xendit_status)->toBe('PENDING');

    $transaksi = $this->invoice->transaksiPaymentGateways()->latest()->first();
    expect($transaksi)->not->toBeNull()
        ->and($transaksi->channel)->toBe(GatewayChannel::Invoice);
});

test('pelanggan otomatis mendapatkan link baru jika link lama sudah expired', function () {
    $this->invoice->update([
        'xendit_invoice_id' => 'inv_old_123',
        'xendit_invoice_url' => 'https://checkout-staging.xendit.co/v2/inv_old_123',
        'xendit_status' => 'EXPIRED',
        'xendit_expired_at' => Carbon::now()->subDay(),
    ]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar')
        ->assertRedirect();

    $this->invoice->refresh();
    expect($this->invoice->xendit_invoice_id)->not->toBe('inv_old_123')
        ->and($this->invoice->xendit_status)->toBe('PENDING');
});

test('pelanggan dapat memicu sinkronkanStatus secara manual pada portal', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('sinkronkanStatus')
        ->assertHasNoErrors();
});

test('pelanggan dapat mencetak invoice PDF miliknya sendiri', function () {
    $response = $this->actingAs($this->akun, 'pelanggan')
        ->get(route('portal.invoice.cetak', $this->invoice));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('pelanggan tidak dapat mencetak invoice PDF milik pelanggan lain (403)', function () {
    $pelangganLain = Pelanggan::factory()->create(['email' => 'other_print@test.com']);
    $invoiceLain = Invoice::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'layanan_pelanggan_id' => $this->layanan->id,
    ]);

    $response = $this->actingAs($this->akun, 'pelanggan')
        ->get(route('portal.invoice.cetak', $invoiceLain));

    $response->assertForbidden();
});

test('tamu tidak dapat mencetak invoice tanpa otentikasi', function () {
    $this->get(route('invoice.cetak', $this->invoice))
        ->assertRedirect(route('login'));

    $this->get(route('portal.invoice.cetak', $this->invoice))
        ->assertRedirect(route('portal.login'));
});
