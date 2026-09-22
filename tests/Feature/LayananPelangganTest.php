<?php

use App\Enums\JenisKoneksi;
use App\Enums\JenisTagihanPertama;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Enums\UserStatus;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Livewire\LayananPelanggan\Create;
use App\Livewire\LayananPelanggan\Edit;
use App\Livewire\LayananPelanggan\Index;
use App\Models\Invoice;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Promo;
use App\Models\PromoPenggunaan;
use App\Models\Router;
use App\Models\User;
use App\Services\Billing\BillingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        // Step 2
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', $validUsername)
        ->set('jenis_koneksi', 'pppoe')
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'full_bulan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    Queue::assertNotPushed(ProvisionPppoeAccountJob::class);

    $layanan = LayananPelanggan::where('ppp_username', $validUsername)->first();
    expect($layanan)->not->toBeNull()
        ->and($layanan->provisioning_status)->toBe(ProvisioningStatus::Failed)
        ->and($layanan->last_provisioning_error)->not->toBeNull()
        ->and($layanan->pelanggan_id)->toBe($this->pelanggan->id)
        ->and($layanan->paket_layanan_id)->toBe($this->paket->id)
        ->and($layanan->router_id)->toBe($this->router->id)
        ->and($layanan->ip_pool_id)->toBe($this->ipPool->id)
        ->and($layanan->ip_static)->toBeNull()
        ->and($layanan->status)->toBe(StatusLayanan::Proses)
        ->and($layanan->site_id)->toStartWith('SITE-');

    // PPP password harus di-generate otomatis oleh sistem (8 karakter alfanumerik), tidak pernah
    // mengambil input manual staf -- lihat CONTEXT.md "PPP Password Credential".
    $rawPass = DB::table('layanan_pelanggan')->where('id', $layanan->id)->value('ppp_password_terenkripsi');
    $decrypted = Crypt::decryptString($rawPass);
    expect($decrypted)->toHaveLength(8)
        ->and($decrypted)->toMatch('/^[a-zA-Z0-9]{8}$/');
});

test('membuka create tanpa permission layanan_pelanggan.buat ditolak', function () {
    $teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $teknisi->assignRole('teknisi');

    Livewire::actingAs($teknisi)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->assertForbidden();
});

test('membuka create dengan pelanggan tidak valid menghasilkan 404', function () {
    $this->actingAs($this->admin)
        ->get(route('layanan-pelanggan.create', 999999))
        ->assertNotFound();
});

test('pelanggan_id terkunci pada route binding dan tidak bisa diubah dari client', function () {
    $pelangganLain = Pelanggan::factory()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->assertSet('pelanggan_id', $this->pelanggan->id);

    expect(fn () => $component->set('pelanggan_id', $pelangganLain->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('validasi step 1 gagal jika paket layanan belum dipilih', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', null)
        ->call('nextStep')
        ->assertHasErrors(['paket_layanan_id' => 'required'])
        ->assertSet('step', 1);
});

test('validasi gagal dengan pesan jelas jika router belum dipilih di step 2', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', null)
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['router_id' => 'required']);
});

test('validasi gagal jika ip_pool_id kosong pada koneksi pppoe', function () {
    $routerWithoutPool = Router::factory()->online()->create();

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $routerWithoutPool->id)
        ->set('jenis_koneksi', 'pppoe')
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['ip_pool_id' => 'required']);
});

test('validasi gagal jika ip_pool_id dari router lain dipilih pada create', function () {
    $routerLain = Router::factory()->online()->create();
    $poolRouterLain = IpPool::factory()->create(['router_id' => $routerLain->id]);

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('jenis_koneksi', 'pppoe')
        ->set('ip_pool_id', $poolRouterLain->id)
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['ip_pool_id']);
});

test('sistem dengan 1 router online otomatis auto-select router_id dan ip_pool_id', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertSet('router_id', $this->router->id)
        ->assertSet('ip_pool_id', $this->ipPool->id);
});

test('sistem dengan banyak router online tidak auto-select router_id saat mount', function () {
    Router::factory()->online()->create();

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertSet('router_id', null)
        ->assertSet('ip_pool_id', null);
});

test('memilih router dengan 1 pool otomatis auto-select ip_pool_id', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->assertSet('ip_pool_id', $this->ipPool->id);
});

test('router offline tetap tampil dan dapat dipilih pada form create billing', function () {
    $offlineRouter = Router::factory()->create([
        'nama_router' => 'Router-Offline-Test',
        'status_koneksi' => StatusRouter::Offline,
    ]);
    $offlinePool = IpPool::factory()->create(['router_id' => $offlineRouter->id]);

    Queue::fake([ProvisionPppoeAccountJob::class]);
    $validUsername = "{$this->pelanggan->no_reg}_00001";

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertSee('Router-Offline-Test')
        ->set('router_id', $offlineRouter->id)
        ->set('ip_pool_id', $offlinePool->id)
        ->set('ppp_username', $validUsername)
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'full_bulan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan = LayananPelanggan::where('ppp_username', $validUsername)->first();
    expect($layanan)->not->toBeNull()
        ->and($layanan->router_id)->toBe($offlineRouter->id);
});

test('admin dapat mengedit layanan meskipun router terkait sedang offline', function () {
    $offlineRouter = Router::factory()->create([
        'nama_router' => 'Router-Offline-Edit',
        'status_koneksi' => StatusRouter::Offline,
    ]);
    $offlinePool = IpPool::factory()->create(['router_id' => $offlineRouter->id]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $offlineRouter->id,
        'ip_pool_id' => $offlinePool->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ppp_username' => "{$this->pelanggan->no_reg}_00001",
    ]);

    Livewire::actingAs($this->admin)
        ->test(Edit::class, ['layananPelanggan' => $layanan])
        ->assertSee('Router-Offline-Edit')
        ->set('nama_site', 'Titik Pasang Baru')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan->refresh();
    expect($layanan->router_id)->toBe($offlineRouter->id)
        ->and($layanan->nama_site)->toBe('Titik Pasang Baru');
});

test('memilih router dengan banyak pool tidak auto-select ip_pool_id', function () {
    $pool2 = IpPool::factory()->create(['router_id' => $this->router->id]);

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->assertSet('ip_pool_id', null);
});

test('admin can create layanan pelanggan with ip_static', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    $validUsername = "{$this->pelanggan->no_reg}_00001";

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('jenis_koneksi', 'ip_static')
        ->set('ip_static', '192.168.100.25')
        ->set('ppp_username', $validUsername)
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'full_bulan')
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
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('jenis_koneksi', 'ip_static')
        ->set('ip_static', 'bukan-ip-valid')
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['ip_static' => 'ipv4']);
});

test('mount dengan pelanggan otomatis auto-fill ppp_username dengan format baru', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan]);

    $pppUsername = $component->get('ppp_username');
    expect($pppUsername)->toMatch('/^'.preg_quote($this->pelanggan->no_reg, '/').'_[0-9]{5}$/');
    expect(LayananPelanggan::extractCounter($pppUsername))->toBeBetween(10000, 99999);
});

test('ppp_username dengan format lama (bebas) ditolak validasi', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ppp_username', 'user_budi_01')  // format lama — harus ditolak
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['ppp_username' => 'regex']);
});

test('ppp_username format benar tapi prefix no_reg salah ditolak', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ppp_username', 'WRONGREG_00001')  // prefix tidak cocok no_reg
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

test('validasi gagal jika ip_pool_id dari router lain dipilih pada edit', function () {
    $routerLain = Router::factory()->online()->create();
    $poolRouterLain = IpPool::factory()->create(['router_id' => $routerLain->id]);

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
        ->set('ip_pool_id', $poolRouterLain->id)
        ->call('save')
        ->assertHasErrors(['ip_pool_id']);
});

test('admin can regenerate ppp password and it is revealed once on the edit page', function () {
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'ppp_password_terenkripsi' => 'password_lama',
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(Edit::class, ['layananPelanggan' => $layanan])
        ->call('regeneratePppPassword')
        ->assertSet('generatedPppPassword', fn ($value) => strlen($value) === 8 && ctype_alnum($value));

    $newPassword = $component->get('generatedPppPassword');
    $component->assertSee($newPassword);

    $layanan->refresh();
    expect($layanan->ppp_password_terenkripsi)->toBe($newPassword)
        ->and($layanan->ppp_password_terenkripsi)->not->toBe('password_lama');
});

test('teknisi without layanan_pelanggan.ubah permission cannot open edit page to regenerate ppp password', function () {
    $teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $teknisi->assignRole('teknisi');

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'ppp_password_terenkripsi' => 'password_lama',
    ]);

    Livewire::actingAs($teknisi)
        ->test(Edit::class, ['layananPelanggan' => $layanan])
        ->assertForbidden();

    expect($layanan->fresh()->ppp_password_terenkripsi)->toBe('password_lama');
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
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', $newUsername)
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'full_bulan')
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
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('router_id', $secondRouter->id)
        ->set('ip_pool_id', $secondPool->id)
        ->set('ppp_username', $newUsername)
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'full_bulan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    expect(LayananPelanggan::where('pelanggan_id', $this->pelanggan->id)->count())->toBe(2);
});

test('registrasi dengan tagihan full 1 bulan membuat invoice sebesar harga paket penuh', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    $paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 200000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $validUsername = "{$this->pelanggan->no_reg}_00001";

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', $validUsername)
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'full_bulan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan = LayananPelanggan::where('ppp_username', $validUsername)->first();
    $invoice = Invoice::where('layanan_pelanggan_id', $layanan->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->periode_tagihan)->toBeNull()
        ->and((float) $invoice->jumlah)->toBe(200000.0)
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(200000.0)
        ->and($invoice->promo_id)->toBeNull();
});

test('registrasi dengan tagihan proporsional sisa hari membuat invoice sesuai perhitungan BillingService', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    $paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 300000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $tanggalMulai = now()->startOfMonth()->addDays(10); // pertengahan bulan
    $validUsername = "{$this->pelanggan->no_reg}_00001";

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', $validUsername)
        ->set('tanggal_mulai', $tanggalMulai->toDateString())
        ->set('jenis_tagihan_pertama', 'prorata')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan = LayananPelanggan::where('ppp_username', $validUsername)->first();
    $invoice = Invoice::where('layanan_pelanggan_id', $layanan->id)->first();

    $expected = app(BillingService::class)->hitungRincianTagihanPertama(
        $paket,
        $tanggalMulai,
        JenisTagihanPertama::ProporsionalSisaHari,
    );

    expect($invoice)->not->toBeNull()
        ->and($invoice->periode_tagihan)->toBeNull()
        ->and((float) $invoice->jumlah_setelah_promo)->toBe($expected['jumlah_setelah_promo'])
        ->and((float) $invoice->jumlah_setelah_promo)->toBeLessThan(300000.0);
});

test('registrasi dengan tagihan promo memotong harga dan mencatat penggunaan promo', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    $paket = PaketLayanan::factory()->create([
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 250000,
        'masa_aktif_nilai' => 1,
        'masa_aktif_satuan' => 'bulan',
    ]);
    $promo = Promo::factory()->create([
        'diskon_nilai' => 25000,
        'minimal_nominal_invoice' => 100000,
        'kuota_global' => 100,
        'terpakai_global' => 0,
    ]);
    $validUsername = "{$this->pelanggan->no_reg}_00001";

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', $validUsername)
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'promo')
        ->set('promo_id', $promo->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('layanan-pelanggan.index'));

    $layanan = LayananPelanggan::where('ppp_username', $validUsername)->first();
    $invoice = Invoice::where('layanan_pelanggan_id', $layanan->id)->first();

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->jumlah)->toBe(250000.0)
        ->and((float) $invoice->jumlah_setelah_promo)->toBe(225000.0)
        ->and($invoice->promo_id)->toBe($promo->id);

    expect(PromoPenggunaan::where('promo_id', $promo->id)->where('invoice_id', $invoice->id)->exists())->toBeTrue()
        ->and($promo->fresh()->terpakai_global)->toBe(1);
});

test('validasi gagal jika opsi promo dipilih tanpa memilih promo_id', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'promo')
        ->call('save')
        ->assertHasErrors(['promo_id']);
});

test('validasi gagal jika jenis_tagihan_pertama belum dipilih', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', "{$this->pelanggan->no_reg}_00001")
        ->set('tanggal_mulai', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['jenis_tagihan_pertama' => 'required']);
});

test('layanan pelanggan tidak tersimpan jika pembuatan tagihan pertama gagal (atomicity)', function () {
    $mockBilling = Mockery::mock(BillingService::class);
    $mockBilling->shouldReceive('generateFirstInvoice')
        ->once()
        ->andThrow(new Exception('Simulasi kegagalan penerbitan tagihan pertama.'));
    $this->instance(BillingService::class, $mockBilling);

    $validUsername = "{$this->pelanggan->no_reg}_00001";

    Livewire::actingAs($this->admin)
        ->test(Create::class, ['pelanggan' => $this->pelanggan])
        ->set('paket_layanan_id', $this->paket->id)
        ->call('nextStep')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->ipPool->id)
        ->set('ppp_username', $validUsername)
        ->set('tanggal_mulai', now()->toDateString())
        ->set('jenis_tagihan_pertama', 'full_bulan')
        ->call('save')
        ->assertHasErrors(['jenis_tagihan_pertama']);

    expect(LayananPelanggan::where('ppp_username', $validUsername)->exists())->toBeFalse();
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

test('handles invalid encrypted ppp password gracefully without throwing DecryptException', function () {
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
    ]);

    DB::table('layanan_pelanggan')->where('id', $layanan->id)->update([
        'ppp_password_terenkripsi' => 'invalid_encrypted_data',
    ]);

    $layanan->refresh();

    expect($layanan->ppp_password_terenkripsi)->toBeNull();
    expect($layanan->toArray()['ppp_password_terenkripsi'])->toBeNull();
});

test('index expiry filter narrows to aktif or suspend layanan within the Perlu Perhatian window', function () {
    $layanan = fn (StatusLayanan $status, int $hariKeExpired) => LayananPelanggan::factory()->create([
        'status' => $status,
        'tanggal_expired' => today()->addDays($hariKeExpired)->toDateString(),
    ]);

    $lewat = $layanan(StatusLayanan::Suspend, -3);
    $segera = $layanan(StatusLayanan::Aktif, 2);
    $lawas = $layanan(StatusLayanan::Suspend, -45);
    $berhenti = $layanan(StatusLayanan::Berhenti, -3);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('expiry', 'overdue')
        ->assertSee($lewat->site_id)
        ->assertDontSee($segera->site_id)
        ->assertDontSee($lawas->site_id)
        ->assertDontSee($berhenti->site_id)
        ->set('expiry', 'soon')
        ->assertSee($segera->site_id)
        ->assertDontSee($lewat->site_id)
        ->set('expiry', 'all')
        ->assertSee($lewat->site_id)
        ->assertSee($segera->site_id)
        ->assertDontSee($lawas->site_id);
});
