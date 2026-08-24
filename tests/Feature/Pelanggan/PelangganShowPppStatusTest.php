<?php

use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Show;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->router = Router::factory()->online()->create([
        'nama_router' => 'Router-Core-01',
        'ip_address' => '192.168.80.92',
        'port' => 8728,
    ]);

    $this->profil = ProfilBandwidth::factory()->create([
        'nama_bandwidth' => 'Profile-Home-20M',
        'max_limit_tx' => 20,
        'max_limit_rx' => 20,
    ]);

    $this->paket = PaketLayanan::factory()->create([
        'nama_paket' => 'Paket Rumah Hemat 20M',
        'profil_bandwidth_id' => $this->profil->id,
        'harga' => 250000,
    ]);

    $this->pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Ahmad',
        'nama_belakang' => 'Dahlan',
    ]);

    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'router_id' => $this->router->id,
        'paket_layanan_id' => $this->paket->id,
        'ppp_username' => 'BF2408202601_00099',
        'ppp_password_terenkripsi' => 'unms9988',
        'site_id' => 'SITE-TEST99',
        'status' => StatusLayanan::Aktif,
    ]);
});

test('detail pelanggan displays Router & Paket Aktif section with connected PPP status', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);

    $mockService->shouldReceive('getPppStatus')
        ->andReturn([
            'is_connected' => true,
            'status_label' => 'Connected',
            'profile' => 'Profile-Home-20M',
            'service' => 'pppoe',
            'ip_address' => '10.0.0.88',
            'local_address' => '10.0.0.1',
            'uptime' => '3d 12:45:10',
            'caller_id' => 'AA:BB:CC:DD:EE:FF',
            'last_logged_out' => 'Belum ada sesi',
            'is_disabled' => false,
            'router_online' => true,
            'error_message' => null,
        ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan->fresh()])
        ->set('activeTab', 'subscriptions')
        ->assertOk()
        ->assertSee('Router')
        ->assertSee('Paket Aktif')
        ->assertSee('Router-Core-01')
        ->assertSee('Paket Rumah Hemat 20M')
        ->assertSee('BF2408202601_00099')
        ->assertSee('Connected (Online)')
        ->assertSee('Profile-Home-20M')
        ->assertSee('10.0.0.88')
        ->assertSee('3d 12:45:10')
        ->assertSee('AA:BB:CC:DD:EE:FF')
        ->assertSee('false (Aktif)');
});

test('detail pelanggan displays disconnected status when PPP session is offline', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);

    $mockService->shouldReceive('getPppStatus')
        ->andReturn([
            'is_connected' => false,
            'status_label' => 'Disconnected',
            'profile' => 'Profile-Home-20M',
            'service' => 'pppoe',
            'ip_address' => null,
            'local_address' => '10.0.0.1',
            'uptime' => null,
            'caller_id' => null,
            'last_logged_out' => 'aug/24/2026 21:00:00',
            'is_disabled' => false,
            'router_online' => true,
            'error_message' => null,
        ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan->fresh()])
        ->set('activeTab', 'subscriptions')
        ->assertOk()
        ->assertSee('Disconnected (Offline)')
        ->assertSee('Belum tersambung (Offline)')
        ->assertSee('aug/24/2026 21:00:00');
});

test('detail pelanggan handles unreachable router gracefully without breaking page', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);

    $mockService->shouldReceive('getPppStatus')
        ->andReturn([
            'is_connected' => false,
            'status_label' => 'Unknown',
            'profile' => null,
            'service' => null,
            'ip_address' => null,
            'local_address' => null,
            'uptime' => null,
            'caller_id' => null,
            'last_logged_out' => null,
            'is_disabled' => false,
            'router_online' => false,
            'error_message' => 'Connection timed out',
        ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan->fresh()])
        ->set('activeTab', 'subscriptions')
        ->assertOk()
        ->assertSee('Router tidak dapat dihubungi');
});

test('refreshPppStatus action updates live PPP status', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);

    $mockService->shouldReceive('getPppStatus')
        ->andReturn([
            'is_connected' => true,
            'status_label' => 'Connected',
            'profile' => 'Profile-Home-20M',
            'service' => 'pppoe',
            'ip_address' => '10.0.0.99',
            'local_address' => '10.0.0.1',
            'uptime' => '5m 12s',
            'caller_id' => '11:22:33:44:55:66',
            'last_logged_out' => null,
            'is_disabled' => false,
            'router_online' => true,
            'error_message' => null,
        ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan->fresh()])
        ->set('activeTab', 'subscriptions')
        ->call('refreshPppStatus')
        ->assertOk()
        ->assertSee('10.0.0.99')
        ->assertSee('5m 12s');
});
