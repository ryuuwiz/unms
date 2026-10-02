<?php

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Exceptions\MikrotikConnectionException;
use App\Exceptions\MikrotikException;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
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

test('pingRouter yang gagal mencatat ping terakhir tanpa mengubah status router', function () {
    $router = Router::factory()->create([
        'ip_address' => '192.0.2.1', // Non-routable test IP
        'port' => 8728,
        'username' => 'admin',
        'password_terenkripsi' => 'password',
        'status_koneksi' => StatusRouter::Online,
    ]);

    expect($this->service->pingRouter($router, 1))->toBeFalse();

    $router->refresh();
    expect($router->status_koneksi)->toBe(StatusRouter::Online)
        ->and($router->last_ping_status)->toBe('failed')
        ->and($router->last_ping_at)->not->toBeNull()
        ->and(MikrotikJobLog::count())->toBe(0);
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

    expect($res)->toMatchArray(['total' => 2, 'synced' => 2, 'errors' => []])
        ->and($names)->toBe(['=name=P10', '=name=EXPIRED']);
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

    expect($res)->toMatchArray(['total' => 2, 'synced' => 2, 'errors' => []]);
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

    expect($res['profile_lama_dihapus'])->toBe(['Karyawan_100Mbps', 'ISOLIR'])
        ->and($dihapus)->toBe(['=.id=*P2', '=.id=*P4']);
});

test('isolir membuat pool dan profile EXPIRED dari config di router tanpa pool isolir, memindahkan secret tanpa disable, dan memutus sesi; buka isolir mengembalikan profile paket', function () {
    [$router, $layanan] = layananPppoeDinamis();
    $reads = [
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']],
        '/ppp/active/print' => [['.id' => '*A', 'name' => $layanan->ppp_username]],
    ];

    [$client, $sent] = fakeRouterOs($reads);
    $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client);

    $pool = $router->fresh()->ipPoolIsolir;
    expect($pool)->not->toBeNull()
        ->and($pool->only(['nama_pool', 'ip_network', 'cidr', 'rentang_ip_awal', 'rentang_ip_akhir']))->toBe([
            'nama_pool' => 'EXPIRED', 'ip_network' => '172.30.0.0', 'cidr' => 16,
            'rentang_ip_awal' => '172.30.0.2', 'rentang_ip_akhir' => '172.30.255.254',
        ])
        ->and(sentAttributes($sent, '/ip/pool/add'))->toBe(['name' => 'EXPIRED', 'ranges' => '172.30.0.2-172.30.255.254'])
        ->and(sentAttributes($sent, '/ppp/profile/add'))->toBe(['name' => 'EXPIRED', 'rate-limit' => '256k/256k', 'local-address' => '172.30.0.1', 'remote-address' => 'EXPIRED'])
        ->and(sentAttributes($sent, '/ppp/secret/set'))->toMatchArray(['.id' => '*1', 'profile' => 'EXPIRED', 'disabled' => 'no'])
        ->and(sentAttributes($sent, '/ppp/active/remove'))->toMatchArray(['.id' => '*A']);

    [$client, $sent] = fakeRouterOs($reads);
    $this->service->bukaIsolirPppoeSecret($router->fresh(), $layanan, $client);

    expect(sentAttributes($sent, '/ppp/secret/set'))->toMatchArray(['profile' => 'P10', 'disabled' => 'no'])
        ->and(sentAttributes($sent, '/ppp/active/remove'))->toMatchArray(['.id' => '*A']);
});

test('pool dan profile EXPIRED mengikuti subnet dan rate-limit di config, dan provisi layanan Suspend memakai EXPIRED', function () {
    config(['mikrotik.isolir_subnet' => '10.250.0.0/24', 'mikrotik.isolir_rate_limit' => '128k/128k']);
    [$router, $layanan] = layananPppoeDinamis();
    Queue::fake();
    $layanan->update(['status' => StatusLayanan::Suspend]);

    [$client, $sent] = fakeRouterOs();
    $this->service->createOrUpdatePppoeSecret($router->fresh(), $layanan->fresh(), $client);

    $ditambahkan = fn (string $endpoint) => collect($sent)->filter(fn ($q) => $q->getEndpoint() === $endpoint)
        ->map(fn ($q) => $q->getAttributes())->first(fn ($attrs) => in_array('=name=EXPIRED', $attrs, true));

    expect($ditambahkan('/ip/pool/add'))->toBe(['=name=EXPIRED', '=ranges=10.250.0.2-10.250.0.254'])
        ->and($ditambahkan('/ppp/profile/add'))->toBe(['=name=EXPIRED', '=rate-limit=128k/128k', '=local-address=10.250.0.1', '=remote-address=EXPIRED'])
        ->and(sentAttributes($sent, '/ppp/secret/add'))->toMatchArray(['profile' => 'EXPIRED', 'disabled' => 'no']);
});

test('isolir ditolak tanpa menimpa apa pun bila subnet isolir bentrok dengan pool NOC di router atau pool paket billing', function () {
    [$router, $layanan] = layananPppoeDinamis();

    $secret = ['/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']]];
    $tulis = fn ($sent) => collect($sent)->map(fn ($q) => $q->getEndpoint())->intersect(['/ip/pool/add', '/ip/pool/set', '/ppp/profile/add', '/ppp/secret/set']);

    [$client, $sent] = fakeRouterOs($secret + ['/ip/pool/print' => [['.id' => '*9', 'name' => 'noc-mgmt', 'ranges' => '10.9.0.2-10.9.0.9,172.30.5.10-172.30.5.20']]]);
    expect(fn () => $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client))
        ->toThrow(MikrotikException::class, 'noc-mgmt');
    expect($tulis($sent))->toBeEmpty();

    $layanan->ipPool->update(['nama_pool' => 'Pool-Lama', 'ip_network' => '172.30.1.0', 'cidr' => 24, 'rentang_ip_awal' => '172.30.1.2', 'rentang_ip_akhir' => '172.30.1.254']);
    [$client, $sent] = fakeRouterOs($secret);
    expect(fn () => $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client))
        ->toThrow(MikrotikException::class, 'Pool-Lama');
    expect($tulis($sent))->toBeEmpty()
        ->and(IpPool::where('nama_pool', 'EXPIRED')->exists())->toBeFalse();
});

test('pool isolir lama pilihan admin yang tumpang tindih tidak menghalangi EXPIRED dan tidak dihapus', function () {
    [$router, $layanan] = layananPppoeDinamis();
    $lama = IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Isolir', 'ip_network' => '172.30.9.0', 'cidr' => 24, 'rentang_ip_awal' => '172.30.9.2', 'rentang_ip_akhir' => '172.30.9.254']);
    $router->update(['ip_pool_isolir_id' => $lama->id]);
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']],
        '/ip/pool/print' => [['.id' => '*7', 'name' => 'Pool-Isolir', 'ranges' => '172.30.9.2-172.30.9.254']],
    ]);

    $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client);
    (new MikrotikService)->isolirPppoeSecret($router->fresh(), $layanan, $client);

    expect($router->fresh()->ipPoolIsolir->nama_pool)->toBe('EXPIRED')
        ->and($lama->fresh()->canBeDeleted())->toBeTrue()
        ->and(sentAttributes($sent, '/ppp/secret/set'))->toMatchArray(['profile' => 'EXPIRED'])
        ->and(sentAttributes($sent, '/ip/pool/remove'))->toBeNull();
});

test('pool bernama EXPIRED di router yang belum dicatat billing tidak diambil alih walau rentangnya sama', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']],
        '/ip/pool/print' => [['.id' => '*8', 'name' => 'EXPIRED', 'ranges' => '172.30.0.2-172.30.255.254']],
    ]);

    expect(fn () => $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client))
        ->toThrow(MikrotikException::class, 'EXPIRED');
    expect(sentAttributes($sent, '/ip/pool/set'))->toBeNull()
        ->and(IpPool::where('nama_pool', 'EXPIRED')->exists())->toBeFalse();
});

test('rentang alamat/cidr di pool NOC dihitung dari awal jaringannya', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']],
        '/ip/pool/print' => [['.id' => '*9', 'name' => 'noc', 'ranges' => '172.31.0.5/15']],
    ]);

    expect(fn () => $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client))
        ->toThrow(MikrotikException::class, 'noc');
});

test('pool DB bernama EXPIRED yang dipakai Router Paket tidak diambil alih sebagai pool isolir', function () {
    [$router, $layanan] = layananPppoeDinamis();
    $layanan->ipPool->update(['nama_pool' => 'EXPIRED']);
    [$client] = fakeRouterOs(['/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']]]);

    expect(fn () => $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client))
        ->toThrow(MikrotikException::class, 'dipakai paket');
    expect($router->fresh()->ip_pool_isolir_id)->toBeNull();
});

test('alamat interface NOC di dalam subnet isolir dianggap bentrok, dan rentang router yang rusak diabaikan', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']],
        '/ip/pool/print' => [['.id' => '*9', 'name' => 'rusak', 'ranges' => '10.0.0.0/40,bukan-ip']],
        '/ip/address/print' => [['.id' => '*2', 'address' => '172.30.200.1/24', 'interface' => 'vlan-mgmt']],
    ]);

    expect(fn () => $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client))
        ->toThrow(MikrotikException::class, 'vlan-mgmt');
    expect(sentAttributes($sent, '/ip/pool/add'))->toBeNull();
});

test('subnet isolir config dinormalisasi ke alamat jaringan, dan config tanpa cidr ditolak sebagai tidak valid', function () {
    [$router, $layanan] = layananPppoeDinamis();
    $reads = ['/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10']]];

    config(['mikrotik.isolir_subnet' => '172.30.5.7/16']);
    [$client, $sent] = fakeRouterOs($reads);
    $this->service->isolirPppoeSecret($router->fresh(), $layanan, $client);

    expect($router->fresh()->ipPoolIsolir->ip_network)->toBe('172.30.0.0')
        ->and(sentAttributes($sent, '/ppp/profile/add'))->toMatchArray(['local-address' => '172.30.0.1']);

    config(['mikrotik.isolir_subnet' => '172.30.0.0']);
    expect(fn () => (new MikrotikService)->isolirPppoeSecret($router->fresh(), $layanan, fakeRouterOs($reads)[0]))
        ->toThrow(MikrotikException::class, 'tidak valid');
});

test('sinkronisasi profile melaporkan kegagalan EXPIRED terpisah agar NOC bisa diberi tahu', function () {
    [$router] = layananPppoeDinamis();
    [$client] = fakeRouterOs(['/ip/pool/print' => [['.id' => '*9', 'name' => 'noc', 'ranges' => '172.30.0.10-172.30.0.20']]]);

    $res = $this->service->syncPaketProfiles($router->fresh(), $client);

    expect($res['isolir_error'])->toContain('noc')
        ->and($res['errors'])->toContain($res['isolir_error']);
});

test('sinkronisasi profile router selalu menyiapkan pool dan profile EXPIRED walau router belum punya IP Pool Isolir', function () {
    [$router] = layananPppoeDinamis();
    expect($router->ip_pool_isolir_id)->toBeNull();

    [$client, $sent] = fakeRouterOs();
    $res = $this->service->syncPaketProfiles($router->fresh(), $client);

    expect($res['errors'])->toBe([])
        ->and($router->fresh()->ipPoolIsolir?->nama_pool)->toBe('EXPIRED')
        ->and(collect($sent)->filter(fn ($q) => $q->getEndpoint() === '/ppp/profile/add')
            ->contains(fn ($q) => in_array('=name=EXPIRED', $q->getAttributes(), true)))->toBeTrue();
});

test('rekonsiliasi memindahkan secret Suspend dari profile ISOLIR lama ke EXPIRED dan memutus sesinya, tanpa menghapus ISOLIR yang masih dipakai', function () {
    [$router, $layanan] = layananPppoeDinamis();
    Queue::fake();
    $layanan->update(['status' => StatusLayanan::Suspend]);
    [$client, $sent] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'ISOLIR', 'password' => 'secret123']],
        '/ppp/profile/print' => [['.id' => '*P4', 'name' => 'ISOLIR']],
        '/ppp/active/print' => [['.id' => '*A', 'name' => $layanan->ppp_username]],
    ]);

    $res = $this->service->autoRecoverPppSecrets($router->fresh(), $client);

    expect(sentAttributes($sent, '/ppp/secret/set'))->toMatchArray(['.id' => '*1', 'profile' => 'EXPIRED'])
        ->and(sentAttributes($sent, '/ppp/active/remove'))->toMatchArray(['.id' => '*A'])
        ->and(sentAttributes($sent, '/ip/pool/remove'))->toBeNull()
        ->and($res['profile_lama_dihapus'])->toBe([]);
});

test('autoRecoverPppSecrets treats a disabled secret as drift because isolir no longer disables', function () {
    [$router, $layanan] = layananPppoeDinamis();
    [$client] = fakeRouterOs([
        '/ppp/secret/print' => [['.id' => '*1', 'name' => $layanan->ppp_username, 'profile' => 'P10', 'password' => 'secret123', 'disabled' => 'true']],
    ]);

    $res = $this->service->autoRecoverPppSecrets($router->fresh(), $client, dryRun: true);

    expect($res['dry_run_changes'][0]['reason'])->toBe('disabled_lama');
});
