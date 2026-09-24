<?php

use App\Livewire\IpPool\Create;
use App\Livewire\IpPool\Edit;
use App\Livewire\IpPool\Index;
use App\Models\IpPool;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
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
        'start' => '192.168.88.2',
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
        ->assertSet('rentang_ip_awal', '10.0.0.2')
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
        ->and($pool->rentang_ip_awal)->toBe('10.0.0.2')
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

test('cannot reassign ip pool to another router while it has existing customer service relations', function () {
    $router = Router::factory()->create();
    $otherRouter = Router::factory()->create();
    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
        'nama_pool' => 'POOL_IN_USE',
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
        ->test(Edit::class, ['pool' => $pool])
        ->set('router_id', $otherRouter->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($pool->fresh()->router_id)->toBe($router->id);
});

test('can reassign ip pool without existing customer service relations to another router', function () {
    $router = Router::factory()->create();
    $otherRouter = Router::factory()->create();
    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
        'nama_pool' => 'POOL_UNUSED',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Edit::class, ['pool' => $pool])
        ->set('router_id', $otherRouter->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('ip-pool.index'));

    expect($pool->fresh()->router_id)->toBe($otherRouter->id);
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

/**
 * @param  array<string, mixed>  $overrides
 */
function isiFormIpPool(mixed $component, Router $router, array $overrides = []): mixed
{
    $data = array_merge([
        'nama_pool' => 'POOL_X',
        'router_id' => $router->id,
        'ip_network' => '10.0.0.0',
        'cidr' => 24,
        'rentang_ip_awal' => '10.0.0.2',
        'rentang_ip_akhir' => '10.0.0.254',
    ], $overrides);

    foreach ($data as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

test('ip pool range must sit inside the network', function () {
    $router = Router::factory()->create();

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $router, [
        'rentang_ip_akhir' => '10.0.1.10',
    ])->call('save')->assertHasErrors(['rentang_ip_akhir']);

    expect(IpPool::count())->toBe(0);
});

test('ip pool range start must not exceed end', function () {
    $router = Router::factory()->create();

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $router, [
        'rentang_ip_awal' => '10.0.0.200',
        'rentang_ip_akhir' => '10.0.0.100',
    ])->call('save')->assertHasErrors(['rentang_ip_akhir']);
});

test('ip pool range must not overlap another pool on the same router', function () {
    $router = Router::factory()->create();
    IpPool::factory()->create([
        'router_id' => $router->id,
        'rentang_ip_awal' => '10.0.0.2',
        'rentang_ip_akhir' => '10.0.0.100',
    ]);

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $router, [
        'rentang_ip_awal' => '10.0.0.50',
        'rentang_ip_akhir' => '10.0.0.150',
    ])->call('save')->assertHasErrors(['rentang_ip_akhir']);
});

test('ip pool range may overlap a pool on a different router', function () {
    $lain = Router::factory()->create();
    IpPool::factory()->create(['router_id' => $lain->id, 'rentang_ip_awal' => '10.0.0.2', 'rentang_ip_akhir' => '10.0.0.254']);
    $router = Router::factory()->create();

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $router)
        ->call('save')->assertHasNoErrors();
});

test('editing a pool does not treat its own range as an overlap', function () {
    $pool = IpPool::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Edit::class, ['pool' => $pool])
        ->call('save')
        ->assertHasNoErrors();
});

test('pool name is unique per router, not globally', function () {
    $routerA = Router::factory()->create();
    $routerB = Router::factory()->create();
    IpPool::factory()->create(['router_id' => $routerA->id, 'nama_pool' => 'Pool-Rumah']);

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $routerA, ['nama_pool' => 'Pool-Rumah'])
        ->call('save')->assertHasErrors(['nama_pool']);

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $routerB, ['nama_pool' => 'Pool-Rumah'])
        ->call('save')->assertHasNoErrors();
});

test('pool name rejects spaces and special characters -- RouterOS remote-address requires a clean identifier', function () {
    $router = Router::factory()->create();

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $router, ['nama_pool' => 'Pool Rumah 01'])
        ->call('save')->assertHasErrors(['nama_pool']);

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $router, ['nama_pool' => 'Pool-Rumah_01'])
        ->call('save')->assertHasNoErrors();
});

test('totalAddresses counts the inclusive range and containsAddress checks membership', function () {
    $pool = IpPool::factory()->create([
        'router_id' => Router::factory()->create()->id,
        'rentang_ip_awal' => '10.0.0.2',
        'rentang_ip_akhir' => '10.0.0.254',
    ]);

    expect($pool->totalAddresses())->toBe(253)
        ->and($pool->containsAddress('10.0.0.2'))->toBeTrue()
        ->and($pool->containsAddress('10.0.0.254'))->toBeTrue()
        ->and($pool->containsAddress('10.0.0.1'))->toBeFalse()
        ->and($pool->containsAddress('10.0.1.5'))->toBeFalse();
});

test('ip pool range cannot cover an ip publik or ip_static already on the same router', function () {
    $router = Router::factory()->create();
    IpPublik::factory()->create(['router_id' => $router->id, 'alamat_ip' => '10.0.0.77']);

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $router, ['nama_pool' => 'Pool-Bentrok'])
        ->call('save')
        ->assertHasErrors('rentang_ip_akhir');

    $lain = Router::factory()->create();
    LayananPelanggan::factory()->create(['router_id' => $lain->id, 'ip_static' => '10.0.0.88']);

    isiFormIpPool(Livewire::actingAs($this->superAdmin)->test(Create::class), $lain, ['nama_pool' => 'Pool-Statis'])
        ->call('save')
        ->assertHasErrors('rentang_ip_akhir');
});

test('ip pool index shows live usage from the router for online routers', function () {
    $router = Router::factory()->online()->create();
    IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Live', 'rentang_ip_awal' => '10.0.0.2', 'rentang_ip_akhir' => '10.0.0.11']);

    $mock = Mockery::mock(MikrotikService::class);
    $mock->shouldReceive('getPoolUsage')->once()->andReturn(['Pool-Live' => 9]);
    app()->instance(MikrotikService::class, $mock);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->assertSee('Terpakai 9 / 10')
        ->assertSee('hampir penuh');
});
