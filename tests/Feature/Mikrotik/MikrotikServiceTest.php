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
use App\Models\RouterPaket;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

test('createOrUpdatePppoeSecret throws MikrotikException if the paket is not registered to the router', function () {
    $router = Router::factory()->online()->create();
    $otherRouter = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Home-10M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);
    $poolLain = IpPool::factory()->create(['router_id' => $otherRouter->id, 'nama_pool' => 'Pool-Rumah']);
    RouterPaket::create(['paket_layanan_id' => $paket->id, 'router_id' => $otherRouter->id, 'ip_pool_id' => $poolLain->id]);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ppp_username' => "{$pelanggan->no_reg}_12345",
        'ppp_password_terenkripsi' => 'secret123',
    ]);

    expect(fn () => $this->service->createOrUpdatePppoeSecret($router, $layanan))
        ->toThrow(MikrotikException::class, "belum didaftarkan ke router {$router->nama_router}");
});

test('ensurePaketProfile creates a profile named after the paket using the chosen pool, without comment', function () {
    $router = Router::factory()->online()->create();
    IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Rumah', 'ip_network' => '10.0.0.0', 'cidr' => 24]);
    $dipilih = IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Karyawan', 'ip_network' => '11.0.0.0', 'cidr' => 24]);
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'BW-100M', 'max_limit_tx' => 100, 'max_limit_rx' => 100]);
    $paket = PaketLayanan::factory()->create(['nama_paket' => 'Karyawan_100Mbps', 'profil_bandwidth_id' => $profil->id]);
    $routerPaket = RouterPaket::create(['paket_layanan_id' => $paket->id, 'router_id' => $router->id, 'ip_pool_id' => $dipilih->id]);
    [$client, $sent] = fakeRouterOs();

    $name = $this->service->ensurePaketProfile($routerPaket, $client);

    $attrs = sentAttributes($sent, '/ppp/profile/add');
    expect($name)->toBe('Karyawan_100Mbps')
        ->and($attrs)->toMatchArray([
            'name' => 'Karyawan_100Mbps',
            'local-address' => '11.0.0.1',
            'remote-address' => 'Pool-Karyawan',
            'rate-limit' => $profil->routerOsRateLimit(),
        ])
        ->and($attrs)->not->toHaveKey('comment');
});

test('syncPaketProfiles syncs one profile per Router Paket of that router only', function () {
    [$router] = layananPppoeDinamis();
    layananPppoeDinamis(); // Router Paket di router lain tidak ikut
    [$client, $sent] = fakeRouterOs();

    $res = $this->service->syncPaketProfiles($router->fresh(), $client);

    $names = collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/profile/add')
        ->map(fn ($q) => collect($q->getAttributes())->first(fn ($w) => str_starts_with($w, '=name=')))
        ->values()->all();

    expect($res)->toMatchArray(['total' => 1, 'synced' => 1, 'errors' => []])
        ->and($names)->toBe(['=name=P10']);
});

test('syncPaketProfilesUsingPool only syncs paket profiles that use the given pool, not the whole router', function () {
    [$router, $layanan] = layananPppoeDinamis();
    daftarkanRouterPaket($layanan);
    $poolLain = IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Lain']);
    $paketLain = PaketLayanan::factory()->create(['nama_paket' => 'P20', 'profil_bandwidth_id' => ProfilBandwidth::factory()->create(['nama_bandwidth' => 'P20'])->id]);
    RouterPaket::create(['paket_layanan_id' => $paketLain->id, 'router_id' => $router->id, 'ip_pool_id' => $poolLain->id]);
    [$client, $sent] = fakeRouterOs();

    $res = $this->service->syncPaketProfilesUsingPool($router->fresh(), $layanan->ipPool, $client);

    $names = collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/profile/add')
        ->map(fn ($q) => collect($q->getAttributes())->first(fn ($w) => str_starts_with($w, '=name=')))
        ->values()->all();

    expect($res)->toMatchArray(['total' => 1, 'synced' => 1, 'errors' => []])
        ->and($names)->toBe(['=name=P10']);
});

test('syncIpPool dismantles the old pool chain by setting next-pool to none', function () {
    $router = Router::factory()->online()->create();
    $pool = IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-A']);
    [$client, $sent] = fakeRouterOs(['/ip/pool/print' => [['.id' => '*A', 'name' => 'Pool-A', 'next-pool' => 'Pool-B']]]);

    $this->service->syncIpPool($router, $pool, $client);

    expect(sentAttributes($sent, '/ip/pool/set'))->toMatchArray(['.id' => '*A', 'next-pool' => 'none']);
});

test('createOrUpdatePppoeSecret for dynamic PPPoE sends no local/remote-address and uses the per-router profile', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client, $sent] = fakeRouterOs();

    $this->service->createOrUpdatePppoeSecret($router, $layanan, $client);

    $attrs = sentAttributes($sent, '/ppp/secret/add');
    expect($attrs)->toMatchArray(['profile' => 'P10'])
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
    // Ganti ip_static memicu provisi ulang lewat observer; tes ini menguji service langsung.
    Queue::fake();
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
            'profile' => 'P10',
            'remote-address' => '10.0.0.7',
            'local-address' => '10.0.0.1',
            'password' => 'secret123',
        ]],
    ]);

    $res = $this->service->autoRecoverPppSecrets($router->fresh(), $client, dryRun: true);

    expect($res['dry_run_changes'])->toHaveCount(1)
        ->and($res['dry_run_changes'][0]['reason'])->toBe('remote_address_mismatch')
        ->and($res['dry_run_changes'][0]['expected'])->toMatchArray([
            'profile' => 'P10',
            'remote-address' => '',
            'local-address' => '',
        ]);
});

test('autoRecoverPppSecrets treats the old per-pool profile as drift so secrets migrate to the per-router profile', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10@Pool-Rumah', 'password' => 'secret123']],
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

test('syncPaketProfiles adds the missing profile when the router has none', function () {
    [$router] = layananPppoeDinamis();

    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('query')->andReturnSelf();
    $mockClient->shouldReceive('read')->andReturn([]);

    $res = $this->service->syncPaketProfiles($router, $mockClient);

    expect($res)->toMatchArray(['total' => 1, 'synced' => 1, 'errors' => []]);
});

test('autoRecoverPppSecrets retries on initial query timeout and recovers secret', function () {
    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Fast-20M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    RouterPaket::create(['paket_layanan_id' => $paket->id, 'router_id' => $router->id, 'ip_pool_id' => IpPool::factory()->create(['router_id' => $router->id])->id]);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ppp_username' => 'test-retry-user',
        'ppp_password_terenkripsi' => 'secret123',
        'status' => StatusLayanan::Aktif,
    ]);

    $mockService = Mockery::mock(MikrotikService::class)->makePartial();
    $mockService->shouldReceive('syncPaketProfiles')->andReturn(['total' => 1, 'synced' => 1, 'errors' => []]);

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

test('autoRecoverPppSecrets treats a legacy UNMS comment as drift so the comment gets cleared', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'comment' => 'UNMS: S1 - Budi', 'profile' => 'P10', 'password' => 'secret123']],
    ]);

    $res = $this->service->autoRecoverPppSecrets($router->fresh(), $client, dryRun: true);

    expect($res['dry_run_changes'][0]['reason'])->toBe('comment_lama');
});

test('autoRecoverPppSecrets removes unused legacy profiles but keeps paket, in-use, and manual profiles', function () {
    [$router, $layanan] = layananPppoeDinamis();
    ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Karyawan_100Mbps']);
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [
            ['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10', 'password' => 'secret123'],
            ['.id' => '*2', 'name' => 'manual_noc', 'profile' => 'Karyawan_100Mbps@Pool-Rumah'],
        ],
        '/ppp/profile/print' => [
            ['.id' => '*P1', 'name' => 'P10'],
            ['.id' => '*P2', 'name' => 'Karyawan_100Mbps'],
            ['.id' => '*P3', 'name' => 'Karyawan_100Mbps@Pool-Rumah'],
            ['.id' => '*P4', 'name' => 'ISOLIR'],
        ],
    ]);

    $res = $this->service->autoRecoverPppSecrets($router->fresh(), $client);

    $dihapus = collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/profile/remove')
        ->map(fn ($q) => $q->getAttributes()[0])->values()->all();

    expect($res['profile_lama_dihapus'])->toBe(['Karyawan_100Mbps'])
        ->and($dihapus)->toBe(['=.id=*P2']);
});

test('isolir memindahkan secret ke profile ISOLIR dari IP Pool Isolir, tidak men-disable, dan memutus sesi; buka isolir mengembalikan profile paket', function () {
    [$router, $layanan] = layananPppoeDinamis();
    $router->update(['ip_pool_isolir_id' => IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Isolir', 'ip_network' => '172.16.99.0', 'cidr' => 24])->id]);
    $reads = [
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']],
        '/ppp/active/print' => [['.id' => '*A', 'name' => $layanan->ppp_username]],
    ];

    [$client, $sent] = fakeRouterOs($reads);
    $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client);

    expect(sentAttributes($sent, '/ppp/profile/add'))->toBe(['name' => 'ISOLIR', 'local-address' => '172.16.99.1', 'remote-address' => 'Pool-Isolir'])
        ->and(sentAttributes($sent, '/ppp/secret/set'))->toMatchArray(['.id' => '*1', 'profile' => 'ISOLIR', 'disabled' => 'no'])
        ->and(sentAttributes($sent, '/ppp/active/remove'))->toMatchArray(['.id' => '*A']);

    [$client, $sent] = fakeRouterOs($reads);
    $this->service->bukaIsolirPppoeSecret($router->fresh(), $layanan, $client);

    expect(sentAttributes($sent, '/ppp/secret/set'))->toMatchArray(['profile' => 'P10', 'disabled' => 'no'])
        ->and(sentAttributes($sent, '/ppp/active/remove'))->toMatchArray(['.id' => '*A']);
});

test('isolir gagal jelas bila router belum punya IP Pool Isolir, dan provisi layanan Suspend memakai profile ISOLIR', function () {
    [$router, $layanan] = layananPppoeDinamis();
    Queue::fake();
    $layanan->update(['status' => StatusLayanan::Suspend]);

    expect(fn () => $this->service->isolirPppoeSecret($router, $layanan, fakeRouterOs()[0]))
        ->toThrow(MikrotikException::class, 'belum punya IP Pool Isolir');

    $router->update(['ip_pool_isolir_id' => IpPool::factory()->create(['router_id' => $router->id])->id]);
    [$client, $sent] = fakeRouterOs();
    $this->service->createOrUpdatePppoeSecret($router->fresh(), $layanan->fresh(), $client);

    expect(sentAttributes($sent, '/ppp/secret/add'))->toMatchArray(['profile' => 'ISOLIR', 'disabled' => 'no']);
});

test('autoRecoverPppSecrets treats a disabled secret as drift because isolir no longer disables', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10', 'password' => 'secret123', 'disabled' => 'true']],
    ]);

    $res = $this->service->autoRecoverPppSecrets($router->fresh(), $client, dryRun: true);

    expect($res['dry_run_changes'][0]['reason'])->toBe('disabled_lama');
});
