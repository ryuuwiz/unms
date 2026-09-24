<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\StatusLayanan;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusDivisiTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Jobs\Mikrotik\UpdatePppoeProfileJob;
use App\Livewire\Ticket\Create as TicketCreate;
use App\Livewire\Ticket\Show;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');

    $this->pelanggan = Pelanggan::factory()->create();
    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $this->paketLain = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);

    $this->router = Router::factory()->online()->create();
    $this->ipPool = IpPool::factory()->create(['router_id' => $this->router->id]);

    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Aktif,
        'router_id' => $this->router->id,
        'ip_pool_id' => $this->ipPool->id,
        'ppp_username' => 'sudah_ada_123',
    ]);

    Livewire::actingAs($this->admin)
        ->test(TicketCreate::class)
        ->set('jenis', JenisTicket::Gangguan->value)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('layanan_pelanggan_id', $this->layanan->id)
        ->set('pic_id', $this->noc->id)
        ->set('deskripsi', 'Pelanggan lapor koneksi putus-putus.')
        ->call('save')
        ->assertHasNoErrors();

    $this->ticket = Ticket::where('layanan_pelanggan_id', $this->layanan->id)->firstOrFail();
});

test('NOC memproses tiket: pilih Sudah Registrasi Mikrotik tidak memanggil RouterOS dan mencatat histori', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);
    $mockService->shouldNotReceive('createOrUpdatePppoeSecret');

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesModeMikrotik', 'sudah')
        ->set('prosesRouterId', $this->router->id)
        ->set('prosesPaketLayananId', $this->paket->id)
        ->set('prosesCatatan', 'PPP sudah dibuat manual sebelumnya oleh NOC.')
        ->set('prosesStatusDivisi', 'selesai')
        ->call('prosesDivisiSubmit')
        ->assertHasNoErrors();

    expect($this->ticket->fresh()->statusDivisi(DivisiTicket::Noc))->toBe(StatusDivisiTicket::Selesai)
        ->and(MikrotikJobLog::count())->toBe(0)
        ->and($this->ticket->fresh()->histori()->latest()->first()->catatan)
        ->toContain('PPP sudah dibuat manual sebelumnya oleh NOC.');
});

test('NOC memproses tiket: pilih Proses Registrasi Mikrotik memanggil RouterOS dan mencatat MikrotikJobLog sukses', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);
    $mockService->shouldReceive('createOrUpdatePppoeSecret')->once();

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesModeMikrotik', 'proses')
        ->set('prosesRouterId', $this->router->id)
        ->set('prosesPaketLayananId', $this->paket->id)
        ->set('prosesCatatan', 'Registrasi ulang PPP di router.')
        ->call('prosesDivisiSubmit')
        ->assertHasNoErrors();

    expect(MikrotikJobLog::where('status', MikrotikJobStatus::Success)->count())->toBe(1);
});

test('NOC memilih router baru ikut menyimpan IP Pool sehingga provisi menerima layanan dengan IP Pool router tsb', function () {
    $routerBaru = Router::factory()->online()->create();
    $poolBaru = IpPool::factory()->create(['router_id' => $routerBaru->id]);

    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);
    $mockService->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->withArgs(fn (Router $router, LayananPelanggan $layanan) => $router->is($routerBaru) && $layanan->ipPool?->is($poolBaru));

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesModeMikrotik', 'proses')
        ->set('prosesRouterId', $routerBaru->id)
        ->assertSet('prosesIpPoolId', $poolBaru->id)
        ->set('prosesCatatan', 'Pindah router karena gangguan.')
        ->call('prosesDivisiSubmit')
        ->assertHasNoErrors();

    expect($this->layanan->fresh())
        ->router_id->toBe($routerBaru->id)
        ->ip_pool_id->toBe($poolBaru->id);
});

test('NOC wajib memilih IP Pool milik router terpilih untuk layanan PPPoE', function () {
    $routerLain = Router::factory()->online()->create();
    $poolRouterLain = IpPool::factory()->create(['router_id' => $routerLain->id]);

    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);
    $mockService->shouldNotReceive('createOrUpdatePppoeSecret');

    $component = Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesCatatan', 'Registrasi PPP di router.');

    $component->set('prosesIpPoolId', null)->call('prosesDivisiSubmit')->assertHasErrors(['prosesIpPoolId' => 'required']);
    $component->set('prosesIpPoolId', $poolRouterLain->id)->call('prosesDivisiSubmit')->assertHasErrors(['prosesIpPoolId' => 'exists']);
});

test('NOC memilih Paket Berbeda mengubah paket layanan lewat UbahPaketLayananAction', function () {
    Queue::fake([UpdatePppoeProfileJob::class]);

    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);
    $mockService->shouldReceive('createOrUpdatePppoeSecret')->once();

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesPilihanPaket', 'berbeda')
        ->set('prosesPaketLayananId', $this->paketLain->id)
        ->set('prosesRouterId', $this->router->id)
        ->set('prosesCatatan', 'Upgrade paket sesuai permintaan pelanggan.')
        ->call('prosesDivisiSubmit')
        ->assertHasNoErrors();

    expect($this->layanan->fresh()->paket_layanan_id)->toBe($this->paketLain->id);
    Queue::assertPushed(UpdatePppoeProfileJob::class);
});

test('NOC mode PPP manual mewajibkan username unik', function () {
    $lain = LayananPelanggan::factory()->create(['ppp_username' => 'dipakai_orang_lain']);

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesPppMode', 'manual')
        ->set('prosesPppUsername', $lain->ppp_username)
        ->set('prosesRouterId', $this->router->id)
        ->set('prosesPaketLayananId', $this->paket->id)
        ->set('prosesCatatan', 'Ganti username manual.')
        ->call('prosesDivisiSubmit')
        ->assertHasErrors(['prosesPppUsername']);
});

test('memilih status Cancel pada modal Proses Divisi membatalkan tiket keseluruhan, bukan status per-divisi', function () {
    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesStatusDivisi', 'cancel')
        ->set('prosesCatatan', 'Pelanggan membatalkan permintaan.')
        ->call('prosesDivisiSubmit')
        ->assertHasNoErrors();

    expect($this->ticket->fresh()->status)->toBe(StatusTicket::Batal)
        ->and($this->ticket->fresh()->statusDivisi(DivisiTicket::Noc))->toBe(StatusDivisiTicket::Belum);
});

test('Catatan Proses wajib diisi saat menyimpan modal Proses Divisi', function () {
    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Noc->value)
        ->set('prosesCatatan', '')
        ->set('prosesRouterId', $this->router->id)
        ->set('prosesPaketLayananId', $this->paket->id)
        ->call('prosesDivisiSubmit')
        ->assertHasErrors(['prosesCatatan']);
});

test('Admin dapat mengubah Paket Layanan lewat modal Proses Admin tanpa menyentuh router', function () {
    Queue::fake([UpdatePppoeProfileJob::class]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->call('openProsesModal', DivisiTicket::Admin->value)
        ->set('prosesUbahPaket', true)
        ->set('prosesPaketLayananId', $this->paketLain->id)
        ->set('prosesCatatan', 'Admin ubah paket sesuai billing.')
        ->call('prosesDivisiSubmit')
        ->assertHasNoErrors();

    expect($this->layanan->fresh()->paket_layanan_id)->toBe($this->paketLain->id)
        ->and($this->layanan->fresh()->router_id)->toBe($this->router->id);
});

test('teknisi tidak berwenang membuka ticket yang bukan PIC-nya (termasuk modal Proses NOC)', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->assertForbidden();
});
