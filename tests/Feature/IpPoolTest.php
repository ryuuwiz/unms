<?php

use App\Livewire\IpPool\Create;
use App\Livewire\IpPool\Index;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Router;
use App\Models\User;
use App\Utils\IpNetworkHelper;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
});

test('suggested ip range calculator works', function () {
    $range = IpNetworkHelper::calculateSuggestedRange('192.168.88.0', 24);

    expect($range)->toBe([
        'start' => '192.168.88.1',
        'end' => '192.168.88.254',
    ]);

    // Invalid CIDR
    $invalidRange = IpNetworkHelper::calculateSuggestedRange('192.168.88.0', 32);
    expect($invalidRange)->toBe([
        'start' => null,
        'end' => null,
    ]);
});

test('can create ip pool and calculate range automatically', function () {
    $router = Router::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Create::class)
        ->set('nama_pool', 'POOL_1')
        ->set('router_id', $router->id)
        ->set('ip_network', '10.0.0.0')
        ->set('cidr', 24)
        ->call('generateRange')
        ->assertHasNoErrors()
        ->assertSet('rentang_ip_awal', '10.0.0.1')
        ->assertSet('rentang_ip_akhir', '10.0.0.254')
        ->set('priority_tx', 8)
        ->set('priority_rx', 8)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('ip-pool.index'));

    $pool = IpPool::where('nama_pool', 'POOL_1')->first();
    expect($pool)->not->toBeNull()
        ->and($pool->ip_network)->toBe('10.0.0.0')
        ->and($pool->cidr)->toBe(24)
        ->and($pool->rentang_ip_awal)->toBe('10.0.0.1')
        ->and($pool->rentang_ip_akhir)->toBe('10.0.0.254')
        ->and($pool->priority_tx)->toBe(8);
});

test('can delete ip pool without relations from index', function () {
    $router = Router::factory()->create();
    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
        'nama_pool' => 'POOL_TO_DELETE',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $pool->id)
        ->assertSet('deletingId', $pool->id)
        ->call('deleteIpPool')
        ->assertSet('deletingId', null)
        ->assertHasNoErrors();

    expect(IpPool::find($pool->id))->toBeNull();
});

test('cannot delete ip pool with existing customer service relations', function () {
    $router = Router::factory()->create();
    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
        'nama_pool' => 'POOL_WITH_LAYANAN',
    ]);

    $pelanggan = Pelanggan::factory()->create();
    $paket = PaketLayanan::factory()->create();

    LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $router->id,
        'ip_pool_id' => $pool->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $pool->id)
        ->call('deleteIpPool')
        ->assertSet('deletingId', null)
        ->assertHasNoErrors();

    expect(IpPool::find($pool->id))->not->toBeNull();
});

test('user without delete permission cannot delete ip pool', function () {
    $router = Router::factory()->create();
    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
    ]);

    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->assignRole('teknisi');

    Livewire::actingAs($unauthorizedUser)
        ->test(Index::class)
        ->call('confirmDelete', $pool->id)
        ->call('deleteIpPool')
        ->assertForbidden();

    expect(IpPool::find($pool->id))->not->toBeNull();
});
