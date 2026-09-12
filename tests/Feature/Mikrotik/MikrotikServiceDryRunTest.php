<?php

use App\Enums\StatusLayanan;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RouterOS\Client;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function makeMockedServiceForDryRun(): MikrotikService
{
    $mockService = Mockery::mock(MikrotikService::class)->makePartial();
    $mockService->shouldReceive('syncAllBandwidthProfiles')->andReturn(['total' => 1, 'synced' => 1, 'errors' => []]);

    return $mockService;
}

test('autoRecoverPppSecrets dryRun=true logs remote-address drift as a change without recovering the secret for real', function () {
    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Fast-20M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ppp_username' => 'dry-run-user',
        'ppp_password_terenkripsi' => 'secret123',
        'ip_pool_id' => null,
        'ip_static' => '10.20.30.5',
        'status' => StatusLayanan::Aktif,
    ]);

    expect($layanan->resolveRemoteAddress())->toBe('10.20.30.5')
        ->and($layanan->resolveLocalAddress())->toBe('10.20.30.1');

    $mockService = makeMockedServiceForDryRun();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('query')->andReturnSelf();
    $client->shouldReceive('read')->andReturn([
        [
            'name' => 'dry-run-user',
            'profile' => 'Profile-Fast-20M',
            'remote-address' => '10.20.30.5/32', // format berbeda dari resolveRemoteAddress(), tapi bukan fokus sprint ini
            'local-address' => '10.20.30.1',
            'password' => 'secret123',
            'disabled' => 'false',
        ],
    ]);

    // Tidak boleh ada satupun aksi nyata yang dijalankan ke router saat dryRun=true.
    $mockService->shouldNotReceive('createOrUpdatePppoeSecret');
    $mockService->shouldNotReceive('disablePppoeSecret');
    $mockService->shouldNotReceive('enablePppoeSecret');

    $stats = $mockService->autoRecoverPppSecrets($router, $client, dryRun: true);

    expect($stats['dry_run'])->toBeTrue()
        ->and($stats['recovered'])->toBe(1)
        ->and($stats['already_synced'])->toBe(0)
        ->and($stats['errors'])->toBeEmpty()
        ->and($stats['dry_run_changes'])->toHaveCount(1);

    $change = $stats['dry_run_changes'][0];
    expect($change['username'])->toBe('dry-run-user')
        ->and($change['action'])->toBe('would_recover')
        ->and($change['reason'])->toBe('remote_address_mismatch')
        ->and($change['expected']['remote-address'])->toBe('10.20.30.5')
        ->and($change['from_router']['remote-address'])->toBe('10.20.30.5/32')
        ->and($change['from_router']['password'])->toBe('[REDACTED]');
});

test('autoRecoverPppSecrets dryRun=true logs disabled-state drift without toggling the secret for real', function () {
    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Fast-20M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ppp_username' => 'dry-run-suspend-user',
        'ppp_password_terenkripsi' => 'secret123',
        'ip_pool_id' => null,
        'ip_static' => '10.20.30.5',
        'status' => StatusLayanan::Suspend,
    ]);

    $mockService = makeMockedServiceForDryRun();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('query')->andReturnSelf();
    $client->shouldReceive('read')->andReturn([
        [
            'name' => 'dry-run-suspend-user',
            'profile' => 'Profile-Fast-20M',
            'remote-address' => '10.20.30.5',
            'local-address' => '10.20.30.1',
            'password' => 'secret123',
            'disabled' => 'false', // Layanan Suspend tapi secret masih enabled di router
        ],
    ]);

    $mockService->shouldNotReceive('createOrUpdatePppoeSecret');
    $mockService->shouldNotReceive('disablePppoeSecret');
    $mockService->shouldNotReceive('enablePppoeSecret');

    $stats = $mockService->autoRecoverPppSecrets($router, $client, dryRun: true);

    expect($stats['dry_run'])->toBeTrue()
        ->and($stats['recovered'])->toBe(1)
        ->and($stats['disabled'])->toBe(0) // dry-run: bukan disable nyata, jadi counter 'disabled' tidak bertambah
        ->and($stats['dry_run_changes'])->toHaveCount(1);

    $change = $stats['dry_run_changes'][0];
    expect($change['action'])->toBe('would_disable')
        ->and($change['reason'])->toBe('disabled_state_mismatch')
        ->and($change['expected']['disabled'])->toBeTrue();
});

test('autoRecoverPppSecrets defaults to dryRun=false and keeps existing real-write behavior', function () {
    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Fast-20M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ppp_username' => 'real-run-user',
        'ppp_password_terenkripsi' => 'secret123',
        'ip_pool_id' => null,
        'ip_static' => '10.20.30.5',
        'status' => StatusLayanan::Aktif,
    ]);

    $mockService = makeMockedServiceForDryRun();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('query')->andReturnSelf();
    $client->shouldReceive('read')->andReturn([]); // secret hilang dari router

    $mockService->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->andReturn(['status' => 'success']);

    $stats = $mockService->autoRecoverPppSecrets($router, $client);

    expect($stats['dry_run'])->toBeFalse()
        ->and($stats['dry_run_changes'])->toBeEmpty()
        ->and($stats['recovered'])->toBe(1);
});
