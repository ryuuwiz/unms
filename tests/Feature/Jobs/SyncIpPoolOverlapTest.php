<?php

use App\Jobs\Mikrotik\SyncIpPoolToRouterJob;
use App\Models\IpPool;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->router = Router::factory()->online()->create();
});

test('dispatching two syncs for the same pool only queues one job', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);

    Queue::fake();

    SyncIpPoolToRouterJob::dispatch($pool);
    SyncIpPoolToRouterJob::dispatch($pool);

    Queue::assertPushed(SyncIpPoolToRouterJob::class, 1);
});

test('syncs for different pools are both queued', function () {
    $poolA = IpPool::factory()->create(['router_id' => $this->router->id]);
    $poolB = IpPool::factory()->create(['router_id' => $this->router->id]);

    Queue::fake();

    SyncIpPoolToRouterJob::dispatch($poolA);
    SyncIpPoolToRouterJob::dispatch($poolB);

    Queue::assertPushed(SyncIpPoolToRouterJob::class, 2);
});

test('middleware() locks the pool sync job per router with WithoutOverlapping', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);

    $middleware = (new SyncIpPoolToRouterJob($pool))->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
    expect($middleware[0]->key)->toBe("mikrotik-router-{$this->router->id}-pool-sync");
    expect($middleware[0]->releaseAfter)->toBe(10);
    expect($middleware[0]->expiresAfter)->toBe(60);
});

test('two different pools on the same router share the same lock key', function () {
    $poolA = IpPool::factory()->create(['router_id' => $this->router->id]);
    $poolB = IpPool::factory()->create(['router_id' => $this->router->id]);

    $jobA = new SyncIpPoolToRouterJob($poolA);
    $jobB = new SyncIpPoolToRouterJob($poolB);

    expect($jobA->middleware()[0]->getLockKey($jobA))
        ->toBe($jobB->middleware()[0]->getLockKey($jobB));
});

test('skips the sync when another pool sync job already holds the router lock', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);

    $lockKey = 'laravel-queue-overlap:'.SyncIpPoolToRouterJob::class.':mikrotik-router-'.$this->router->id.'-pool-sync';
    $lock = Cache::lock($lockKey, 60);
    expect($lock->get())->toBeTrue();

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldNotReceive('syncIpPool');
    app()->instance(MikrotikService::class, $mockService);

    // QUEUE_CONNECTION=sync in tests, so this actually runs the job (and its
    // middleware) synchronously instead of just recording a fake push.
    SyncIpPoolToRouterJob::dispatch($pool);

    $lock->release();
});

test('runs the sync once the router lock is free', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('syncIpPool')
        ->once()
        ->andReturn(['status' => 'success']);
    app()->instance(MikrotikService::class, $mockService);

    SyncIpPoolToRouterJob::dispatch($pool);
});

test('retryUntil is set so repeated lock contention does not trip MaxAttemptsExceededException', function () {
    // Regression for Sentry: "SyncIpPoolToRouterJob has been attempted too many
    // times" (Illuminate\Queue\MaxAttemptsExceededException). Root cause: the
    // Redis queue driver increments a job's `attempts` on every pop even when
    // WithoutOverlapping::release() re-queues it without ever running handle()
    // (lock contested). With only tries=3 and releaseAfter(10), pure lock
    // contention exhausted all 3 tries in ~20-30s and Worker::process() called
    // markJobAsFailedIfAlreadyExceedsMaxAttempts(), which killed the job before
    // it ever attempted a real RouterOS call -- no MikrotikJobLog row, no real
    // exception, just this opaque framework error.
    //
    // Worker::markJobAsFailedIfAlreadyExceedsMaxAttempts() only compares
    // attempts() vs tries when retryUntil() is null; when retryUntil() is a
    // future timestamp it returns early regardless of the attempt count (see
    // vendor/laravel/framework/.../Queue/Worker.php). This asserts the job
    // actually sets that future timestamp, closing the gap that let 3 pure
    // lock-contention pops kill the job before a real attempt ever happened.
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);
    $job = new SyncIpPoolToRouterJob($pool);

    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class);
    expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp());

    // The window must comfortably outlast several lock-contention release cycles
    // (releaseAfter(10) per attempt): anything under a minute would barely help.
    expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addMinutes(5)->getTimestamp());
});
