<?php

use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RouterOS\Client;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
});

function mockPppStatusClient(): Client
{
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('query')->andReturnSelf();
    $mockClient->shouldReceive('read')->andReturn([]);

    return $mockClient;
}

test('getPppStatus hanya memanggil client sekali untuk dua request dalam TTL yang sama', function () {
    $router = Router::factory()->create();
    $mockClient = mockPppStatusClient();

    $service = Mockery::mock(MikrotikService::class)->makePartial();
    $service->shouldReceive('getClient')->once()->andReturn($mockClient);

    $result1 = $service->getPppStatus($router, 'user123');
    $result2 = $service->getPppStatus($router, 'user123');

    expect($result1)->toBe($result2);
});

test('refreshPppStatus bypass cache dan memanggil client lagi', function () {
    $router = Router::factory()->create();
    $mockClient = mockPppStatusClient();

    $service = Mockery::mock(MikrotikService::class)->makePartial();
    $service->shouldReceive('getClient')->twice()->andReturn($mockClient);

    $service->getPppStatus($router, 'user123'); // populate cache
    $service->refreshPppStatus($router, 'user123'); // harus fetch ulang
});

test('cache key berbeda untuk router atau username berbeda', function () {
    $routerA = Router::factory()->create();
    $routerB = Router::factory()->create();
    $mockClient = mockPppStatusClient();

    $service = Mockery::mock(MikrotikService::class)->makePartial();
    $service->shouldReceive('getClient')->twice()->andReturn($mockClient);

    $service->getPppStatus($routerA, 'user123');
    $service->getPppStatus($routerB, 'user123');
});

test('status_timeout dari config dipakai saat membuka koneksi', function () {
    config(['mikrotik.status_timeout' => 7]);

    $router = Router::factory()->create();
    $mockClient = mockPppStatusClient();

    $service = Mockery::mock(MikrotikService::class)->makePartial();
    $service->shouldReceive('getClient')->once()->with($router, 7)->andReturn($mockClient);

    $service->getPppStatus($router, 'user123');
});
