<?php

use App\Enums\StatusRouter;
use App\Exceptions\MikrotikConnectionException;
use App\Exceptions\MikrotikException;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('ensurePppProfile throws MikrotikException if nama_bandwidth is empty', function () {
    $router = Router::factory()->online()->create();
    $profil = new ProfilBandwidth;
    $profil->nama_bandwidth = '';

    expect(fn () => $this->service->ensurePppProfile($router, $profil))
        ->toThrow(MikrotikException::class, 'Nama profil bandwidth di UNMS kosong');
});
