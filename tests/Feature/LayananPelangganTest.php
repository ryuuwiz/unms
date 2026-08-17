<?php

use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Livewire\LayananPelanggan\Create;
use App\Livewire\LayananPelanggan\Index;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->pelanggan = Pelanggan::factory()->create();
    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $this->router = Router::factory()->online()->create();
});

test('admin can create layanan pelanggan through 2-step wizard', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        // Step 1
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        // Step 2
        ->set('router_id', $this->router->id)
        ->set('ppp_username', 'user_test_pppoe')
        ->set('ppp_password', 'secret_ppp_pass')
        ->set('jenis_koneksi', 'pppoe')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan = LayananPelanggan::where('ppp_username', 'user_test_pppoe')->first();
    expect($layanan)->not->toBeNull()
        ->and($layanan->pelanggan_id)->toBe($this->pelanggan->id)
        ->and($layanan->paket_layanan_id)->toBe($this->paket->id)
        ->and($layanan->router_id)->toBe($this->router->id)
        ->and($layanan->status)->toBe(StatusLayanan::Proses)
        ->and($layanan->site_id)->toStartWith('SITE-');

    // Check encrypted PPP password in DB
    $rawPass = DB::table('layanan_pelanggan')->where('id', $layanan->id)->value('ppp_password_terenkripsi');
    expect($rawPass)->not->toBe('secret_ppp_pass')
        ->and(Crypt::decryptString($rawPass))->toBe('secret_ppp_pass');
});

test('can list and filter layanans by status', function () {
    LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertOk()
        ->assertSee($this->pelanggan->nama_depan);
});
