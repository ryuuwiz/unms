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

test('ensurePppProfile per pool creates {bandwidth}@{pool} carrying rate-limit, gateway and pool name', function () {
    $router = Router::factory()->online()->create();
    $pool = IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Rumah', 'ip_network' => '10.0.0.0', 'cidr' => 24]);
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Home-10M', 'max_limit_tx' => 10, 'max_limit_rx' => 10]);
    [$client, $sent] = fakeRouterOs();

    $name = $this->service->ensurePppProfile($router, $profil, $client, $pool);

    $attrs = sentAttributes($sent, '/ppp/profile/add');
    expect($name)->toBe('Home-10M@Pool-Rumah')
        ->and($attrs)->toMatchArray([
            'name' => 'Home-10M@Pool-Rumah',
            'local-address' => '10.0.0.1',
            'remote-address' => 'Pool-Rumah',
        ])
        ->and($attrs)->toHaveKey('rate-limit');
});

test('ensurePppProfile without pool stays a plain profile with no address', function () {
    $router = Router::factory()->online()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Home-10M']);
    [$client, $sent] = fakeRouterOs();

    $name = $this->service->ensurePppProfile($router, $profil, $client);

    expect($name)->toBe('Home-10M')
        ->and(sentAttributes($sent, '/ppp/profile/add'))->not->toHaveKeys(['local-address', 'remote-address']);
});

test('syncAllBandwidthProfiles syncs a plain profile plus one profile per router pool', function () {
    $router = Router::factory()->online()->create();
    IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-A', 'rentang_ip_awal' => '10.0.0.2', 'rentang_ip_akhir' => '10.0.0.50']);
    IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-B', 'ip_network' => '10.0.1.0', 'rentang_ip_awal' => '10.0.1.2', 'rentang_ip_akhir' => '10.0.1.50']);
    ProfilBandwidth::factory()->create(['nama_bandwidth' => 'P10']);
    [$client, $sent] = fakeRouterOs();

    $res = $this->service->syncAllBandwidthProfiles($router->fresh(), $client);

    $names = collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/profile/add')
        ->map(fn ($q) => collect($q->getAttributes())->first(fn ($w) => str_starts_with($w, '=name=')))
        ->sort()->values()->all();

    expect($res['total'])->toBe(3)
        ->and($res['synced'])->toBe(3)
        ->and($names)->toBe(['=name=P10', '=name=P10@Pool-A', '=name=P10@Pool-B']);
});

test('createOrUpdatePppoeSecret for dynamic PPPoE sends no local/remote-address and uses the per-pool profile', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client, $sent] = fakeRouterOs();

    $this->service->createOrUpdatePppoeSecret($router, $layanan, $client);

    $attrs = sentAttributes($sent, '/ppp/secret/add');
    expect($attrs)->toMatchArray(['profile' => 'P10@Pool-Rumah'])
        ->and($attrs)->not->toHaveKeys(['local-address', 'remote-address'])
        ->and($layanan->fresh()->ip_dynamic)->toBeNull();
});

test('createOrUpdatePppoeSecret unsets literal addresses left on an existing dynamic secret', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'remote-address' => '10.0.0.7', 'local-address' => '10.0.0.1']],
    ]);

    $this->service->createOrUpdatePppoeSecret($router, $layanan, $client);

    $unset = collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/secret/unset')
        ->map(fn ($q) => collect($q->getAttributes())->first(fn ($w) => str_starts_with($w, '=value-name=')))
        ->sort()->values()->all();

    expect($unset)->toBe(['=value-name=local-address', '=value-name=remote-address']);
});

test('createOrUpdatePppoeSecret for ip_static keeps a literal secret on the plain profile', function () {
    [$router, $layanan] = layananPppoeDinamis();
    $layanan->update(['ip_static' => '10.0.1.25', 'jenis_koneksi' => JenisKoneksi::IpStatic]);
    [$client, $sent] = fakeRouterOs();

    $this->service->createOrUpdatePppoeSecret($router, $layanan->fresh(), $client);

    expect(sentAttributes($sent, '/ppp/secret/add'))->toMatchArray([
        'profile' => 'P10',
        'remote-address' => '10.0.1.25',
        'local-address' => '10.0.0.1',
    ]);
});

test('autoRecoverPppSecrets dry-run flags a dynamic secret still carrying a literal remote-address and expects it empty', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client] = fakeRouterOs([
        '/ppp/secret/print' => [[
            '.id' => '*1',
            'name' => $layanan->ppp_username,
            'profile' => 'P10@Pool-Rumah',
            'remote-address' => '10.0.0.7',
            'local-address' => '10.0.0.1',
            'password' => 'secret123',
        ]],
    ]);

    $res = $this->service->autoRecoverPppSecrets($router->fresh(), $client, dryRun: true);

    expect($res['dry_run_changes'])->toHaveCount(1)
        ->and($res['dry_run_changes'][0]['reason'])->toBe('remote_address_mismatch')
        ->and($res['dry_run_changes'][0]['expected'])->toMatchArray([
            'profile' => 'P10@Pool-Rumah',
            'remote-address' => '',
            'local-address' => '',
        ]);
});

test('autoRecoverPppSecrets treats the old plain profile as drift so secrets migrate to the per-pool profile', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10', 'password' => 'secret123']],
    ]);

    $res = $this->service->autoRecoverPppSecrets($router->fresh(), $client, dryRun: true);

    expect($res['dry_run_changes'][0]['reason'])->toBe('profile_mismatch');
});

test('getPoolUsage counts /ip/pool/used entries per pool and never throws', function () {
    $router = Router::factory()->online()->create(['ip_address' => '192.0.2.1']);
    $mock = Mockery::mock(MikrotikService::class)->makePartial();
    [$client] = fakeRouterOs(['/ip/pool/used/print' => [
        ['pool' => 'Pool-A', 'address' => '10.0.0.2'],
        ['pool' => 'Pool-A', 'address' => '10.0.0.3'],
        ['pool' => 'Pool-B', 'address' => '10.0.1.2'],
    ]]);
    $mock->shouldReceive('getClient')->once()->andReturn($client);

    expect($mock->getPoolUsage($router))->toBe(['Pool-A' => 2, 'Pool-B' => 1]);

    $offline = Router::factory()->online()->create();
    $gagal = Mockery::mock(MikrotikService::class)->makePartial();
    $gagal->shouldReceive('getClient')->andThrow(new MikrotikConnectionException('down'));

    expect($gagal->getPoolUsage($offline))->toBeNull();
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
