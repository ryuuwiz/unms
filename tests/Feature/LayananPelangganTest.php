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

test('pendaftaran layanan ditolak jika pelanggan sudah memiliki layanan aktif pada router dan paket yang sama', function () {
    // Existing active service
    LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
        'ppp_username' => "{$this->pelanggan->no_reg}_00001",
    ]);

    $newUsername = "{$this->pelanggan->no_reg}_00002";

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', $newUsername)
        ->set('ppp_password', 'secret1234')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['router_id'])
        ->assertSee('Pelanggan ini sudah memiliki Data Registrasi Billing aktif dengan paket yang sama pada router ini');
});

test('pelanggan dapat memiliki banyak layanan jika router atau paket berbeda (multi-site)', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    // Existing active service on router 1
    LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
        'ppp_username' => "{$this->pelanggan->no_reg}_00001",
    ]);

    // Second router for site 2
    $secondRouter = Router::factory()->online()->create(['nama_router' => 'Router-Site-2']);
    $secondPool = IpPool::factory()->create(['router_id' => $secondRouter->id]);
    $newUsername = "{$this->pelanggan->no_reg}_00002";

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('router_id', $secondRouter->id)
        ->set('ip_pool_id', $secondPool->id)
        ->set('ppp_username', $newUsername)
        ->set('ppp_password', 'secret1234')
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    expect(LayananPelanggan::where('pelanggan_id', $this->pelanggan->id)->count())->toBe(2);
});

test('layanan dengan masa aktif expired menampilkan status EXPIRED bukan Suspend', function () {
    $expiredLayanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Suspend,
        'tanggal_expired' => now()->subDay()->toDateString(),
        'ppp_username' => "{$this->pelanggan->no_reg}_00001",
    ]);

    expect($expiredLayanan->isExpired())->toBeTrue()
        ->and($expiredLayanan->statusBadgeLabel())->toBe('EXPIRED')
        ->and($expiredLayanan->statusBadgeColor())->toBe('red')
        ->and($expiredLayanan->isAktif())->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertOk()
        ->assertSee('EXPIRED');
});

test('layanan dengan masa aktif di masa depan menampilkan status aslinya', function () {
    $activeLayanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => now()->addMonth()->toDateString(),
        'ppp_username' => "{$this->pelanggan->no_reg}_00001",
    ]);

    expect($activeLayanan->isExpired())->toBeFalse()
        ->and($activeLayanan->statusBadgeLabel())->toBe('Aktif')
        ->and($activeLayanan->statusBadgeColor())->toBe('green')
        ->and($activeLayanan->isAktif())->toBeTrue();
});

test('admin dapat memfilter layanan berdasarkan status EXPIRED di Data Registrasi Billing', function () {
    $expiredLayanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Suspend,
        'tanggal_expired' => now()->subDays(3)->toDateString(),
        'ppp_username' => "{$this->pelanggan->no_reg}_00001",
    ]);

    $pelangganAktif = Pelanggan::factory()->create(['nama_depan' => 'PelangganAktif']);
    $activeLayanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelangganAktif->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => now()->addDays(20)->toDateString(),
        'ppp_username' => "{$pelangganAktif->no_reg}_00001",
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('filterStatus', 'expired')
        ->assertSee($this->pelanggan->nama_depan)
        ->assertDontSee($pelangganAktif->nama_depan);
});
