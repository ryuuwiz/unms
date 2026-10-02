<?php

use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Show;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');

    $this->pelanggan = Pelanggan::factory()->create();
});

/** Channel di-register ulang pada broadcaster Reverb; phpunit memakai broadcaster null yang mengizinkan semuanya. */
function pakaiBroadcasterReverb(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'key',
        'broadcasting.connections.reverb.secret' => 'secret',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);

    require base_path('routes/channels.php');
}

test('staf yang boleh melihat pelanggan boleh berlangganan channel pelanggan', function () {
    pakaiBroadcasterReverb();

    $this->actingAs($this->noc)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-pelanggan.{$this->pelanggan->id}"])
        ->assertOk();
});

test('user tanpa izin melihat pelanggan ditolak dari channel pelanggan', function () {
    pakaiBroadcasterReverb();

    $tanpaPeran = User::factory()->create(['status' => UserStatus::Active]);

    $this->actingAs($tanpaPeran)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-pelanggan.{$this->pelanggan->id}"])
        ->assertForbidden();
});

test('event status sesi PPP hanya menyegarkan layanan yang disebut', function () {
    $router = Router::factory()->online()->create();
    $layananBerubah = LayananPelanggan::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'router_id' => $router->id, 'ppp_username' => 'BF01_11111']);
    $layananLain = LayananPelanggan::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'router_id' => $router->id, 'ppp_username' => 'BF01_22222']);

    $mikrotik = Mockery::mock(MikrotikService::class);
    $mikrotik->shouldReceive('getPppStatus')->andReturn(['is_connected' => false]);
    $mikrotik->shouldReceive('refreshPppStatus')->once()->with(Mockery::any(), 'BF01_11111')->andReturn(['is_connected' => true]);
    $this->app->instance(MikrotikService::class, $mikrotik);

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('loadPppStatuses')
        ->call('segarkanStatusPppLayanan', ['layanan_id' => $layananBerubah->id])
        ->assertSet("pppStatuses.{$layananBerubah->id}.is_connected", true)
        ->assertSet("pppStatuses.{$layananLain->id}.is_connected", false);
});

test('event untuk layanan pelanggan lain diabaikan', function () {
    $layananOrangLain = LayananPelanggan::factory()->create(['router_id' => Router::factory()->online(), 'ppp_username' => 'BF02_33333']);

    $mikrotik = Mockery::mock(MikrotikService::class);
    $mikrotik->shouldReceive('getPppStatus')->andReturn([]);
    $mikrotik->shouldNotReceive('refreshPppStatus');
    $this->app->instance(MikrotikService::class, $mikrotik);

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('segarkanStatusPppLayanan', ['layanan_id' => $layananOrangLain->id])
        ->assertSet('pppStatuses', []);
});
