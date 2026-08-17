<?php

use App\Livewire\IpPools\Create;
use App\Models\IpPool;
use App\Models\Router;
use App\Models\User;
use App\Utils\IpNetworkHelper;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'admin']);
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
    $user = User::factory()->create();
    $user->assignRole('admin');

    $router = Router::create([
        'name' => 'R1',
        'ip_address' => '127.0.0.1',
        'username' => 'admin',
    ]);

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('name', 'POOL_1')
        ->set('router_id', $router->id)
        ->set('ip_network', '10.0.0.0')
        ->set('cidr', '24')
        ->set('queue_tx_mbps', '10.5')
        ->set('queue_rx_mbps', '5')
        ->call('generateRange')
        ->assertHasNoErrors()
        ->assertSet('ip_range_start', '10.0.0.1')
        ->assertSet('ip_range_end', '10.0.0.254')
        ->call('save')
        ->assertHasNoErrors();

    $pool = IpPool::where('name', 'POOL_1')->first();
    expect($pool)->not->toBeNull()
        ->and($pool->ip_network)->toBe('10.0.0.0')
        ->and($pool->cidr)->toBe(24)
        ->and($pool->ip_range_start)->toBe('10.0.0.1')
        ->and($pool->ip_range_end)->toBe('10.0.0.254')
        ->and((float) $pool->queue_tx_mbps)->toBe(10.5)
        ->and((float) $pool->queue_rx_mbps)->toBe(5.0)
        ->and($pool->priority_tx)->toBe(8);
});
