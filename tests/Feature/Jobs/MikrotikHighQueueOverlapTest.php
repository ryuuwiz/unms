<?php

use App\Jobs\Mikrotik\CleanupPppSecretOnOldRouterJob;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Jobs\Mikrotik\UpdatePppoeProfileJob;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->router = Router::factory()->online()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $this->layanan = LayananPelanggan::factory()->create([
        'router_id' => $this->router->id,
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
    ]);
});

it('locks every per-customer job class under one shared per-router key, separate from reconciliation', function () {
    $jobs = [
        new ProvisionPppoeAccountJob($this->layanan),
        new EnablePppoeAccountJob($this->layanan),
        new DisablePppoeAccountJob($this->layanan),
        new UpdatePppoeProfileJob($this->layanan),
        new CleanupPppSecretOnOldRouterJob($this->router->id, $this->layanan->ppp_username, $this->layanan->id),
    ];

    $lockKeys = [];

    foreach ($jobs as $job) {
        $middleware = $job->middleware();

        expect($middleware)->toHaveCount(1);
        expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
        // ADR-0059: bukan mikrotik-router-{id} (kunci rekonsiliasi router-wide).
        expect($middleware[0]->key)->toBe("mikrotik-layanan-router-{$this->router->id}");
        expect($middleware[0]->shareKey)->toBeTrue();

        $lockKeys[] = $middleware[0]->getLockKey($job);
    }

    // Every job class must resolve to the exact same lock key so they truly
    // serialize against each other, not just against their own class.
    expect(array_unique($lockKeys))->toHaveCount(1);
});

it('releases a second per-customer job back to the queue when another per-customer job holds the router lock', function () {
    $lockKey = 'laravel-queue-overlap:mikrotik-layanan-router-'.$this->router->id;
    $lock = Cache::lock($lockKey, 30);
    expect($lock->get())->toBeTrue();

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldNotReceive('createOrUpdatePppoeSecret');
    $this->app->instance(MikrotikService::class, $mockService);

    // QUEUE_CONNECTION=sync in tests, so dispatch runs the job (and its
    // middleware) synchronously instead of just recording a fake push.
    ProvisionPppoeAccountJob::dispatch($this->layanan);

    $lock->release();
});

it('runs the mikrotik-high job once the shared router lock is free', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->andReturn(['status' => 'success', 'action' => 'created']);
    $this->app->instance(MikrotikService::class, $mockService);

    ProvisionPppoeAccountJob::dispatch($this->layanan);
});

it('does not wait behind router-wide reconciliation holding mikrotik-router-{id}', function () {
    $lock = Cache::lock('laravel-queue-overlap:mikrotik-router-'.$this->router->id, 600);
    expect($lock->get())->toBeTrue();

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->andReturn(['status' => 'success', 'action' => 'created']);
    $this->app->instance(MikrotikService::class, $mockService);

    ProvisionPppoeAccountJob::dispatch($this->layanan);

    $lock->release();
});

it('bounds retries by time, not by lock-contention releases', function () {
    $job = new ProvisionPppoeAccountJob($this->layanan);

    expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addMinutes(9)->getTimestamp())
        ->and($job->maxExceptions)->toBe(5)
        ->and(property_exists($job, 'tries'))->toBeFalse();
});
