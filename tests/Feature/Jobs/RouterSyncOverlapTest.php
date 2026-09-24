<?php

use App\Jobs\Mikrotik\RecoverPppRouterJob;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('dispatching two full-router-sync jobs for the same router only queues one job', function () {
    $router = Router::factory()->create();

    Queue::fake();

    RecoverPppRouterJob::dispatch($router);
    RecoverPppRouterJob::dispatch($router);

    Queue::assertPushed(RecoverPppRouterJob::class, 1);
});

test('full-router-sync jobs for different routers are both queued', function () {
    $routerA = Router::factory()->create();
    $routerB = Router::factory()->create();

    Queue::fake();

    RecoverPppRouterJob::dispatch($routerA);
    RecoverPppRouterJob::dispatch($routerB);

    Queue::assertPushed(RecoverPppRouterJob::class, 2);
});

test('middleware() locks the full-router-sync job per router with WithoutOverlapping', function () {
    $router = Router::factory()->create();

    $middleware = (new RecoverPppRouterJob($router))->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
    expect($middleware[0]->key)->toBe("mikrotik-router-{$router->id}");
    expect($middleware[0]->releaseAfter)->toBe(180);
    expect($middleware[0]->expiresAfter)->toBe(600);
});

test('skips the sync pipeline when another full-router-sync job already holds the router lock', function () {
    $router = Router::factory()->create();

    // Simulate a worker that is already running the sync pipeline for this router by
    // holding the exact WithoutOverlapping lock key ourselves before dispatching.
    $lockKey = 'laravel-queue-overlap:'.RecoverPppRouterJob::class.':mikrotik-router-'.$router->id;
    $lock = Cache::lock($lockKey, 600);
    expect($lock->get())->toBeTrue();

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldNotReceive('autoRecoverPppSecrets');
    $mockService->shouldNotReceive('provisionRouterFull');
    $this->app->instance(MikrotikService::class, $mockService);

    // QUEUE_CONNECTION=sync in tests, so this actually runs the job (and its
    // middleware) synchronously instead of just recording a fake push.
    RecoverPppRouterJob::dispatch($router);

    $lock->release();
});

test('runs the sync pipeline once the router lock is free', function () {
    $router = Router::factory()->create();

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('autoRecoverPppSecrets')
        ->once()
        ->andReturn(['recovered' => 0, 'disabled' => 0, 'duplicates_removed' => 0, 'delete_cap_exceeded' => false, 'errors' => []]);
    $this->app->instance(MikrotikService::class, $mockService);

    RecoverPppRouterJob::dispatch($router);
});
