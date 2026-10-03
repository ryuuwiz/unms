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
use App\Models\PengaturanGateway;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    pakaiGatewayUji();
    PengaturanGateway::create(['provider' => 'uji', 'gateway' => 'uji', 'nama' => 'Gateway Uji', 'is_default' => true, 'is_active' => true, 'sandbox_mode' => true]);

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
    $layananLain = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);
    $invoiceLain = Invoice::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'layanan_pelanggan_id' => $layananLain->id,
        'jumlah' => 100000,
        'jumlah_setelah_promo' => 100000,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $invoiceLain])
        ->assertForbidden();
});

test('pelanggan dapat menekan tombol bayar dan di-redirect ke payment_gateway_url', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar')
        ->assertRedirect();

    $this->invoice->refresh();
    expect($this->invoice->payment_gateway_url)->not->toBeNull()
        ->and($this->invoice->payment_gateway_id)->not->toBeNull()
        ->and($this->invoice->payment_gateway_status)->toBe('PENDING');

    $transaksi = $this->invoice->transaksiPaymentGateways()->latest()->first();
    expect($transaksi)->not->toBeNull()
        ->and($transaksi->channel)->toBe(GatewayChannel::Invoice);
});

test('pelanggan otomatis mendapatkan link baru jika link lama sudah expired', function () {
    $this->invoice->update([
        'payment_gateway_id' => 'inv_old_123',
        'payment_gateway_url' => 'https://gateway-uji.test/bayar/inv_old_123',
        'payment_gateway_status' => 'EXPIRED',
        'payment_gateway_expired_at' => Carbon::now()->subDay(),
    ]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar')
        ->assertRedirect();

    $this->invoice->refresh();
    expect($this->invoice->payment_gateway_id)->not->toBe('inv_old_123')
        ->and($this->invoice->payment_gateway_status)->toBe('PENDING');
});

test('invoice dengan tautan gateway yang sudah dipensiunkan tetap bisa dibuka dan dibayar lewat gateway aktif', function () {
    Exceptions::fake();
    $this->invoice->update([
        'payment_gateway_provider' => 'xendit',
        'payment_gateway_id' => 'xnd_lama_123',
        'payment_gateway_status' => 'EXPIRED',
        'payment_gateway_expired_at' => Carbon::now()->subDay(),
    ]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('bayar')
        ->assertRedirect();

    Exceptions::assertNothingReported();
    expect($this->invoice->refresh()->payment_gateway_provider)->toBe('uji')
        ->and($this->invoice->payment_gateway_status)->toBe('PENDING');
});

test('pelanggan dapat memicu cek status pembayaran secara manual pada portal', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->call('cekStatusPembayaran')
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
    $layananLain = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);
    $invoiceLain = Invoice::factory()->create([
        'pelanggan_id' => $pelangganLain->id,
        'layanan_pelanggan_id' => $layananLain->id,
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

test('tamu dapat membuka rincian tagihan tanpa login lewat tautan bertanda tangan', function () {
    $signedUrl = URL::signedRoute('portal.invoice.show', ['invoice' => $this->invoice->id]);

    $this->get($signedUrl)
        ->assertOk()
        ->assertSee($this->invoice->no_invoice);
});

test('tamu ditolak membuka rincian tagihan tanpa login dan tanpa tanda tangan valid', function () {
    $this->get(route('portal.invoice.show', $this->invoice))
        ->assertForbidden();
});

test('rute lama /bayar tetap berfungsi sebagai redirect ke Halaman Tagihan Mandiri', function () {
    // Halaman estimasi biaya terpisah sudah dihapus (lihat CONTEXT.md "Halaman Tagihan
    // Mandiri") -- rute lama dipertahankan hanya sebagai redirect, bukan dihapus total,
    // untuk tautan /bayar yang mungkin sudah ter-cache di notifikasi lama/riwayat browser.
    $this->get(route('portal.invoice.bayar', $this->invoice))
        ->assertRedirect(route('portal.invoice.show', $this->invoice));
});

test('pelanggan tidak dapat memicu pembayaran untuk invoice yang dibatalkan', function () {
    $this->invoice->update([
        'status' => StatusInvoice::Dibatalkan,
        'keterangan_hapus' => 'Dibatalkan karena koreksi tagihan ganda',
    ]);

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Show::class, ['invoice' => $this->invoice])
        ->assertOk()
        ->assertSee('Tagihan Ini Telah Dibatalkan')
        ->assertSee('Dibatalkan karena koreksi tagihan ganda')
        ->assertDontSee('Bayar Sekarang')
        ->call('bayar')
        ->assertNoRedirect();

    expect($this->invoice->fresh()->payment_gateway_url)->toBeNull();
});
