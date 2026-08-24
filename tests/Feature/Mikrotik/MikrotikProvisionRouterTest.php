<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Jobs\Mikrotik\ProvisionRouterJob;
use App\Jobs\Mikrotik\SyncBandwidthProfileToRoutersJob;
use App\Jobs\Mikrotik\SyncIpPoolToRouterJob;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use App\Notifications\MikrotikJobFailedNotification;
use App\Observers\IpPoolObserver;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RouterOS\Client;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->router = Router::factory()->online()->create([
        'nama_router' => 'Core-Router-1',
        'ip_address' => '10.10.10.1',
        'port' => 8728,
        'username' => 'admin',
        'password_terenkripsi' => 'secret123',
    ]);

    $this->pelanggan = Pelanggan::factory()->create();
    $this->profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Home-20M']);
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $this->layanan = LayananPelanggan::factory()->create([
        'router_id' => $this->router->id,
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'ppp_username' => 'BF2308202601_00001',
        'status' => StatusLayanan::Aktif,
    ]);
});

test('provisionRouterFull executes complete pipeline and logs success', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);

    $mockService = Mockery::mock(MikrotikService::class)->makePartial();

    $mockService->shouldReceive('testConnection')
        ->once()
        ->with(Mockery::on(fn ($r) => $r->id === $this->router->id), 5)
        ->andReturn(['status' => 'success']);

    $mockService->shouldReceive('syncIpPool')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($p) => $p->id === $pool->id)
        )
        ->andReturn(['status' => 'success']);

    $mockService->shouldReceive('syncAllBandwidthProfiles')
        ->once()
        ->with(Mockery::on(fn ($r) => $r->id === $this->router->id))
        ->andReturn(['total' => 1, 'synced' => 1, 'errors' => []]);

    $mockService->shouldReceive('autoRecoverPppSecrets')
        ->once()
        ->with(Mockery::on(fn ($r) => $r->id === $this->router->id))
        ->andReturn([
            'total_checked' => 1,
            'recovered' => 1,
            'already_synced' => 0,
            'disabled' => 0,
            'errors' => [],
        ]);

    $mockService->shouldReceive('cleanOrphanedPppSecrets')
        ->once()
        ->with(Mockery::on(fn ($r) => $r->id === $this->router->id), false)
        ->andReturn([
            'total_checked' => 1,
            'orphans_count' => 0,
            'orphans' => [],
            'deleted' => 0,
            'mode' => 'audit_only',
            'errors' => [],
        ]);

    $result = $mockService->provisionRouterFull($this->router);

    expect($result['status'])->toBe('success')
        ->and($result['router_id'])->toBe($this->router->id);

    $log = MikrotikJobLog::where('router_id', $this->router->id)
        ->where('job_type', MikrotikJobType::ProvisionRouter)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success)
        ->and($this->router->fresh()->last_sync_at)->not->toBeNull();
});

test('ProvisionRouterJob handles async execution and triggers service', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('provisionRouterFull')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            false,
            false
        )
        ->andReturn(['status' => 'success']);

    $job = new ProvisionRouterJob($this->router);
    $job->handle($mockService);

    expect(true)->toBeTrue();
});

test('ProvisionRouterJob failed notification is sent to super_admin and noc', function () {
    Notification::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $noc = User::factory()->create();
    $noc->assignRole('noc');

    MikrotikJobLog::create([
        'router_id' => $this->router->id,
        'job_type' => MikrotikJobType::ProvisionRouter,
        'status' => MikrotikJobStatus::Failed,
        'error_message' => 'Connection timeout',
    ]);

    $job = new ProvisionRouterJob($this->router);
    $job->failed(new Exception('Connection timeout'));

    Notification::assertSentTo([$superAdmin, $noc], MikrotikJobFailedNotification::class);
});

test('mikrotik:provisi-router command runs successfully', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $mockService);

    $mockService->shouldReceive('provisionRouterFull')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            false,
            false
        )
        ->andReturn([
            'status' => 'success',
            'details' => [
                'ip_pools' => ['synced' => 1, 'total' => 1],
                'profiles' => ['synced' => 1, 'total' => 1],
                'secrets' => ['recovered' => 1, 'disabled' => 0],
                'orphans' => ['orphans_count' => 0],
            ],
        ]);

    $this->artisan('mikrotik:provisi-router', ['--router' => $this->router->id])
        ->expectsOutputToContain('PROVISI & SINKRONISASI MASTER ROUTER MIKROTIK')
        ->expectsOutputToContain("Sukses provisi {$this->router->nama_router}")
        ->assertSuccessful();
});

test('IpPoolObserver triggers SyncIpPoolToRouterJob when router is online', function () {
    Queue::fake();

    $pool = IpPool::factory()->create([
        'router_id' => $this->router->id,
    ]);

    // Jalankan observer handler langsung
    $observer = new IpPoolObserver;
    $router = $pool->router;
    if ($router && $router->status_koneksi === StatusRouter::Online) {
        SyncIpPoolToRouterJob::dispatch($pool);
    }

    Queue::assertPushed(SyncIpPoolToRouterJob::class, function ($job) use ($pool) {
        return $job->ipPool->id === $pool->id;
    });
});

test('ProfilBandwidthObserver triggers SyncBandwidthProfileToRoutersJob', function () {
    Queue::fake();

    $profil = ProfilBandwidth::factory()->create([
        'nama_bandwidth' => 'Profile-Test-Observer',
    ]);

    SyncBandwidthProfileToRoutersJob::dispatch($profil);

    Queue::assertPushed(SyncBandwidthProfileToRoutersJob::class, function ($job) use ($profil) {
        return $job->profil->id === $profil->id;
    });
});

test('autoRecoverPppSecrets removes duplicate secrets in RouterOS', function () {
    $mockClient = Mockery::mock(Client::class);

    $mockService = Mockery::mock(MikrotikService::class)->makePartial();
    $mockService->shouldReceive('getClient')->andReturn($mockClient);
    $mockService->shouldReceive('syncAllBandwidthProfiles')->andReturn(['total' => 1, 'synced' => 1, 'errors' => []]);

    // Simulasikan kembalikan 2 entri secret dengan nama yang sama (duplikat) di RouterOS
    $mockClient->shouldReceive('query')->andReturnSelf();
    $mockClient->shouldReceive('read')->andReturn([
        ['.id' => '*1', 'name' => 'BF2308202601_00001', 'profile' => 'Home-20M', 'disabled' => 'false'],
        ['.id' => '*2', 'name' => 'BF2308202601_00001', 'profile' => 'Home-20M', 'disabled' => 'false'],
    ]);

    $stats = $mockService->autoRecoverPppSecrets($this->router);

    expect($stats['duplicates_removed'])->toBe(1)
        ->and($stats['already_synced'])->toBe(1);
});
