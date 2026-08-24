<?php

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Livewire\LayananPelanggan\Create;
use App\Livewire\LayananPelanggan\Edit;
use App\Livewire\LayananPelanggan\Index;
use App\Models\IpPool;
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
use Illuminate\Support\Facades\Queue;
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
    $this->ipPool = IpPool::factory()->create(['router_id' => $this->router->id]);
});

test('admin can create layanan pelanggan through 2-step wizard', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    $validUsername = "{$this->pelanggan->no_reg}_00001";

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
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', $validUsername)
        ->set('ppp_password', 'secret_ppp_pass')
        ->set('jenis_koneksi', 'pppoe')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    Queue::assertPushed(ProvisionPppoeAccountJob::class);

    $layanan = LayananPelanggan::where('ppp_username', $validUsername)->first();
    expect($layanan)->not->toBeNull()
        ->and($layanan->pelanggan_id)->toBe($this->pelanggan->id)
        ->and($layanan->paket_layanan_id)->toBe($this->paket->id)
        ->and($layanan->router_id)->toBe($this->router->id)
        ->and($layanan->ip_pool_id)->toBe($this->ipPool->id)
        ->and($layanan->ip_static)->toBeNull()
        ->and($layanan->status)->toBe(StatusLayanan::Proses)
        ->and($layanan->site_id)->toStartWith('SITE-');

    // Check encrypted PPP password in DB
    $rawPass = DB::table('layanan_pelanggan')->where('id', $layanan->id)->value('ppp_password_terenkripsi');
    expect($rawPass)->not->toBe('secret_ppp_pass')
        ->and(Crypt::decryptString($rawPass))->toBe('secret_ppp_pass');
});

test('validasi step 1 gagal jika pelanggan belum dipilih', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', null)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertHasErrors(['pelanggan_id' => 'required'])
        ->assertSet('step', 1);
});

test('validasi step 1 gagal jika paket layanan belum dipilih', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', null)
        ->call('nextStep')
        ->assertHasErrors(['paket_layanan_id' => 'required'])
        ->assertSet('step', 1);
});

test('validasi gagal dengan pesan jelas jika router belum dipilih di step 2', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', null)
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('ppp_password', 'secret123')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['router_id' => 'required']);
});

test('validasi gagal jika ip_pool_id kosong pada koneksi pppoe', function () {
    $routerWithoutPool = Router::factory()->online()->create();

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $routerWithoutPool->id)
        ->set('jenis_koneksi', 'pppoe')
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('ppp_password', 'secret123')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['ip_pool_id' => 'required']);
});

test('sistem dengan 1 router online otomatis auto-select router_id dan ip_pool_id', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertSet('router_id', $this->router->id)
        ->assertSet('ip_pool_id', $this->ipPool->id);
});

test('sistem dengan banyak router online tidak auto-select router_id saat mount', function () {
    Router::factory()->online()->create();

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertSet('router_id', null)
        ->assertSet('ip_pool_id', null);
});

test('memilih router dengan 1 pool otomatis auto-select ip_pool_id', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->assertSet('ip_pool_id', $this->ipPool->id);
});

test('memilih router dengan banyak pool tidak auto-select ip_pool_id', function () {
    $pool2 = IpPool::factory()->create(['router_id' => $this->router->id]);

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->assertSet('ip_pool_id', null);
});

test('admin can create layanan pelanggan with ip_static', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    $validUsername = "{$this->pelanggan->no_reg}_00001";

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('jenis_koneksi', 'ip_static')
        ->set('ip_static', '192.168.100.25')
        ->set('ppp_username', $validUsername)
        ->set('ppp_password', 'secret_ppp_pass')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan = LayananPelanggan::where('ppp_username', $validUsername)->first();
    expect($layanan)->not->toBeNull()
        ->and($layanan->jenis_koneksi)->toBe(JenisKoneksi::IpStatic)
        ->and($layanan->ip_static)->toBe('192.168.100.25')
        ->and($layanan->ip_pool_id)->toBeNull();
});

test('pendaftaran layanan dengan format ip_static tidak valid ditolak', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('jenis_koneksi', 'ip_static')
        ->set('ip_static', 'bukan-ip-valid')
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('ppp_password', 'secret123')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['ip_static' => 'ipv4']);
});

test('memilih pelanggan di step 1 auto-fill ppp_username dengan format baru', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->assertSet('ppp_username', "{$this->pelanggan->no_reg}_00001");
});

test('ppp_username dengan format lama (bebas) ditolak validasi', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ppp_username', 'user_budi_01')  // format lama — harus ditolak
        ->set('ppp_password', 'secret123')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['ppp_username' => 'regex']);
});

test('ppp_username format benar tapi prefix no_reg salah ditolak', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ppp_username', 'WRONGREG_00001')  // prefix tidak cocok no_reg
        ->set('ppp_password', 'secret123')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['ppp_username' => 'regex']);
});

test('admin can edit layanan pelanggan and switch to ip_static', function () {
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'ip_pool_id' => $this->ipPool->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ppp_username' => "{$this->pelanggan->no_reg}_00001",
    ]);

    Livewire::actingAs($this->admin)
        ->test(Edit::class, ['layananPelanggan' => $layanan])
        ->set('jenis_koneksi', 'ip_static')
        ->set('ip_static', '10.20.30.40')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan->refresh();
    expect($layanan->jenis_koneksi)->toBe(JenisKoneksi::IpStatic)
        ->and($layanan->ip_static)->toBe('10.20.30.40')
        ->and($layanan->ip_pool_id)->toBeNull();
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
