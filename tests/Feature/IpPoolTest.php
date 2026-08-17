<?php

use App\Livewire\IpPool\Create;
use App\Models\IpPool;
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
