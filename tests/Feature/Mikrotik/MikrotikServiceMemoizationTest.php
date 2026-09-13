<?php

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RouterOS\Client;

uses(RefreshDatabase::class);

// Root-cause fix: ensurePppProfile()/syncIpPool() were re-hitting RouterOS on
// every single secret even when the exact profile/pool was just synced
// moments earlier in the same recovery/provisioning run -- this is what
// turned a 300-secret recovery job into 900+ redundant sequential queries
// and spiked router CPU. These tests prove the second call for the same
// router+profil (or router+pool) is served from the in-memory cache instead
// of hitting the RouterOS client again.
test('ensurePppProfile only queries RouterOS once for the same router+profile in one service instance', function () {
    $router = Router::factory()->online()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Cache-10M']);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('query')->twice()->andReturnSelf(); // find (empty) + add
    $mockClient->shouldReceive('read')->twice()->andReturn([]);

    $service = new MikrotikService;

    $first = $service->ensurePppProfile($router, $profil, $mockClient);
    $second = $service->ensurePppProfile($router, $profil, $mockClient);

    expect($first)->toBe('Profile-Cache-10M')
        ->and($second)->toBe('Profile-Cache-10M');
});

test('syncIpPool only queries RouterOS once for the same router+pool in one service instance', function () {
    $router = Router::factory()->online()->create();
    $pool = IpPool::factory()->create(['router_id' => $router->id]);

    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('query')->times(4)->andReturnSelf(); // find+add pool, find+add queue
    $mockClient->shouldReceive('read')->times(4)->andReturn([]);

    $service = new MikrotikService;

    $first = $service->syncIpPool($router, $pool, $mockClient);
    $second = $service->syncIpPool($router, $pool, $mockClient);

    expect($first['status'])->toBe('success')
        ->and($second['status'])->toBe('success');
});

test('createOrUpdatePppoeSecret skips redundant profile sync for repeat secrets sharing a profile', function () {
    $router = Router::factory()->online()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Shared-20M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    $layananA = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'paket_layanan_id' => $paket->id,
        'ppp_username' => 'shared-user-a',
        'jenis_koneksi' => JenisKoneksi::IpStatic,
        'ip_pool_id' => null,
        'ip_static' => '10.10.10.10',
        'status' => StatusLayanan::Aktif,
    ]);
    $layananB = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'paket_layanan_id' => $paket->id,
        'ppp_username' => 'shared-user-b',
        'jenis_koneksi' => JenisKoneksi::IpStatic,
        'ip_pool_id' => null,
        'ip_static' => '10.10.10.11',
        'status' => StatusLayanan::Aktif,
    ]);

    // /ppp/profile/print + /ppp/profile/add fire ONCE (memoized on 2nd secret),
    // then /ppp/secret/print + /ppp/secret/add fire once per secret (2x each = 4).
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('query')->times(6)->andReturnSelf();
    $mockClient->shouldReceive('read')->times(6)->andReturn([]);

    $service = new MikrotikService;

    $service->createOrUpdatePppoeSecret($router, $layananA->fresh(['paketLayanan.profilBandwidth', 'pelanggan']), $mockClient);
    $service->createOrUpdatePppoeSecret($router, $layananB->fresh(['paketLayanan.profilBandwidth', 'pelanggan']), $mockClient);

    expect(true)->toBeTrue(); // Mockery ->times(6) assertion is the real check here.
});
