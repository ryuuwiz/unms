<?php

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Exceptions\MikrotikConnectionException;
use App\Exceptions\MikrotikException;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RouterOS\Client;
use RouterOS\Exceptions\StreamException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = new MikrotikService;
});

test('testConnection throws MikrotikException on unreachable host and updates router status to offline', function () {
    $router = Router::factory()->create([
        'ip_address' => '192.0.2.1', // Non-routable test IP
        'port' => 8728,
        'username' => 'admin',
        'password_terenkripsi' => 'password',
        'status_koneksi' => StatusRouter::Online,
    ]);

    expect(fn () => $this->service->testConnection($router, 1))
        ->toThrow(MikrotikException::class);

    $router->refresh();
    expect($router->status_koneksi)->toBe(StatusRouter::Offline)
        ->and($router->last_ping_status)->toBe('failed')
        ->and($router->last_ping_at)->not->toBeNull();
});

test('getClient throws MikrotikConnectionException on invalid credentials / connection failure', function () {
    $router = Router::factory()->create([
        'ip_address' => '256.256.256.256', // Invalid host
        'port' => 8728,
        'username' => 'admin',
        'password_terenkripsi' => 'password',
    ]);

    expect(fn () => $this->service->getClient($router, 1))
        ->toThrow(MikrotikConnectionException::class);
});

test('createOrUpdatePppoeSecret throws MikrotikException on invalid ppp_username format', function () {
    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Home-10M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ppp_username' => 'user with space!@#',
        'ppp_password_terenkripsi' => 'secret123',
    ]);

    expect(fn () => $this->service->createOrUpdatePppoeSecret($router, $layanan))
        ->toThrow(MikrotikException::class, 'Format username PPPoE');
});

test('createOrUpdatePppoeSecret throws MikrotikException if paket or profil is missing', function () {
    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();

    $layanan = new LayananPelanggan([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'ppp_username' => 'valid-user-123',
        'ppp_password_terenkripsi' => 'secret123',
    ]);

    expect(fn () => $this->service->createOrUpdatePppoeSecret($router, $layanan))
        ->toThrow(MikrotikException::class, 'tidak memiliki paket layanan atau profil bandwidth');
});

test('createOrUpdatePppoeSecret throws MikrotikException if PPPoE connection has no IP Pool', function () {
    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Home-10M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ip_pool_id' => null,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ppp_username' => "{$pelanggan->no_reg}_12345",
        'ppp_password_terenkripsi' => 'secret123',
    ]);

    expect(fn () => $this->service->createOrUpdatePppoeSecret($router, $layanan))
        ->toThrow(MikrotikException::class, 'wajib memiliki alokasi IP Pool yang valid');
});

test('createOrUpdatePppoeSecret throws MikrotikException if IP Pool belongs to a different router', function () {
    $router = Router::factory()->online()->create();
    $otherRouter = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Home-10M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);
    $ipPool = IpPool::factory()->create(['router_id' => $otherRouter->id]);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ip_pool_id' => $ipPool->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ppp_username' => "{$pelanggan->no_reg}_12345",
        'ppp_password_terenkripsi' => 'secret123',
    ]);

    expect(fn () => $this->service->createOrUpdatePppoeSecret($router, $layanan))
        ->toThrow(MikrotikException::class, 'terdaftar pada router lain');
});

test('allocateDynamicIp assigns the first free literal IP from the layanan IP Pool', function () {
    $router = Router::factory()->create();
    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
        'rentang_ip_awal' => '10.0.0.2',
        'rentang_ip_akhir' => '10.0.0.254',
    ]);
    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'ip_pool_id' => $pool->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ip_dynamic' => null,
    ]);

    $this->service->allocateDynamicIp($router, $layanan);

    expect($layanan->ip_dynamic)->toBe('10.0.0.2')
        ->and($layanan->ip_pool_id)->toBe($pool->id)
        ->and($layanan->fresh()->ip_dynamic)->toBe('10.0.0.2');
});

test('allocateDynamicIp is idempotent -- keeps an already-assigned ip_dynamic still valid for its pool', function () {
    $router = Router::factory()->create();
    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
        'rentang_ip_awal' => '10.0.0.2',
        'rentang_ip_akhir' => '10.0.0.254',
    ]);
    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'ip_pool_id' => $pool->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ip_dynamic' => '10.0.0.50',
    ]);

    $this->service->allocateDynamicIp($router, $layanan);

    expect($layanan->ip_dynamic)->toBe('10.0.0.50');
});

test('allocateDynamicIp falls back to another IP Pool on the same router when the selected pool is full', function () {
    $router = Router::factory()->create();
    $poolPenuh = IpPool::factory()->create([
        'router_id' => $router->id,
        'nama_pool' => 'Pool-Penuh',
        'rentang_ip_awal' => '10.0.0.2',
        'rentang_ip_akhir' => '10.0.0.2',
    ]);
    $poolCadangan = IpPool::factory()->create([
        'router_id' => $router->id,
        'nama_pool' => 'Pool-Cadangan',
        'rentang_ip_awal' => '10.0.1.2',
        'rentang_ip_akhir' => '10.0.1.254',
    ]);
    LayananPelanggan::factory()->create(['ip_pool_id' => $poolPenuh->id, 'ip_dynamic' => '10.0.0.2', 'status' => 'aktif']);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'ip_pool_id' => $poolPenuh->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ip_dynamic' => null,
    ]);

    $this->service->allocateDynamicIp($router, $layanan);

    expect($layanan->ip_pool_id)->toBe($poolCadangan->id)
        ->and($layanan->ip_dynamic)->toBe('10.0.1.2');
});

test('allocateDynamicIp throws MikrotikException when every IP Pool on the router is full', function () {
    $router = Router::factory()->create();
    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
        'rentang_ip_awal' => '10.0.0.2',
        'rentang_ip_akhir' => '10.0.0.2',
    ]);
    LayananPelanggan::factory()->create(['ip_pool_id' => $pool->id, 'ip_dynamic' => '10.0.0.2', 'status' => 'aktif']);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'ip_pool_id' => $pool->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ip_dynamic' => null,
        'ppp_username' => 'user-full-pool',
    ]);

    expect(fn () => $this->service->allocateDynamicIp($router, $layanan))
        ->toThrow(MikrotikException::class, 'sudah penuh');
});

test('ensurePppProfile throws MikrotikException if nama_bandwidth is empty', function () {
    $router = Router::factory()->online()->create();
    $profil = new ProfilBandwidth;
    $profil->nama_bandwidth = '';

    expect(fn () => $this->service->ensurePppProfile($router, $profil))
        ->toThrow(MikrotikException::class, 'Nama profil bandwidth di UNMS kosong');
});

test('syncAllBandwidthProfiles bulk fetches profiles and adds missing profile', function () {
    $router = Router::factory()->online()->create();
    ProfilBandwidth::factory()->create([
        'nama_bandwidth' => 'Profile-10M',
        'max_limit_tx' => 10,
        'max_limit_rx' => 10,
    ]);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('query')->andReturnSelf();
    // Return empty existing profiles from router
    $mockClient->shouldReceive('read')->andReturn([]);

    $res = $this->service->syncAllBandwidthProfiles($router, $mockClient);

    expect($res['total'])->toBe(1)
        ->and($res['synced'])->toBe(1)
        ->and($res['errors'])->toBeEmpty();
});

test('autoRecoverPppSecrets retries on initial query timeout and recovers secret', function () {
    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Fast-20M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ppp_username' => 'test-retry-user',
        'ppp_password_terenkripsi' => 'secret123',
        'status' => StatusLayanan::Aktif,
    ]);

    $mockService = Mockery::mock(MikrotikService::class)->makePartial();
    $mockService->shouldReceive('syncAllBandwidthProfiles')->andReturn(['total' => 1, 'synced' => 1, 'errors' => []]);

    $client1 = Mockery::mock(Client::class);
    $client2 = Mockery::mock(Client::class);

    // First attempt fails with Stream timed out
    $client1->shouldReceive('query')->andReturnSelf();
    $client1->shouldReceive('read')->andThrow(new StreamException('Stream timed out'));

    // Second client attempt succeeds with empty secrets list
    $client2->shouldReceive('query')->andReturnSelf();
    $client2->shouldReceive('read')->andReturn([]);

    $mockService->shouldReceive('getClient')->andReturn($client2);

    $mockService->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->andReturn(['status' => 'success']);

    $stats = $mockService->autoRecoverPppSecrets($router, $client1);

    expect($stats['recovered'])->toBe(1)
        ->and($stats['already_synced'])->toBe(0);
});
