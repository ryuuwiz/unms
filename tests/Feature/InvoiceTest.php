<?php

use App\Enums\StatusInvoice;
use App\Enums\UserStatus;
use App\Livewire\Invoice\Create;
use App\Livewire\Invoice\Index;
use App\Livewire\Invoice\Show;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Promo;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->teknisiUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisiUser->assignRole('teknisi');

    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 300000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $this->router = Router::factory()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);
});

test('user with invoice.lihat permission can access invoice index and show', function () {
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
    ]);

    Livewire::actingAs($this->adminUser)->test(Index::class)->assertOk();
    Livewire::actingAs($this->adminUser)->test(Show::class, ['invoice' => $invoice])->assertOk();
});

test('user without invoice.lihat permission cannot access invoice index', function () {
    Livewire::actingAs($this->teknisiUser)->test(Index::class)->assertForbidden();
});

test('admin can create manual invoice with correct auto-number sequence', function () {
    Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('layanan_pelanggan_id', $this->layanan->id)
        ->set('tanggal_jatuh_tempo', now()->addDays(7)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $invoice = Invoice::where('pelanggan_id', $this->pelanggan->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->no_invoice)->toMatch('/^INV-\d{6}-\d{6}$/')
        ->and((float) $invoice->jumlah)->toBe(300000.0)
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(300000.0)
        ->and($invoice->status)->toBe(StatusInvoice::MenungguPembayaran);

    expect(Activity::where('subject_type', Invoice::class)->where('subject_id', $invoice->id)->exists())->toBeTrue();
});

test('admin can apply promo discount when creating invoice', function () {
    $promo = Promo::factory()->create([
        'kode_promo' => 'DISKON50',
        'diskon_nilai' => 50000,
        'diskon_tipe' => 'nominal',
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('layanan_pelanggan_id', $this->layanan->id)
        ->set('promo_id', $promo->id)
        ->set('tanggal_jatuh_tempo', now()->addDays(7)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $invoice = Invoice::where('pelanggan_id', $this->pelanggan->id)->first();
    expect((float) $invoice->jumlah_setelah_promo)->toBe(250000.0)
        ->and($invoice->promo_id)->toBe($promo->id);

    expect($promo->fresh()->terpakai_global)->toBe(1);
});

test('admin can cancel or delete an unpaid invoice', function () {
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->set('deletingId', $invoice->id)
        ->set('keteranganHapus', 'Dibatalkan oleh admin untuk penyesuaian paket')
        ->call('deleteInvoice');

    expect(Invoice::find($invoice->id))->toBeNull()
        ->and(Invoice::withTrashed()->find($invoice->id)->status)->toBe(StatusInvoice::Dibatalkan);
});

test('user with invoice.cetak permission can download pdf', function () {
    $invoice = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('invoice.cetak', $invoice));
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
