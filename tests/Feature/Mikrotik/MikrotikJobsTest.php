<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Mikrotik\PingRouterJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Jobs\Mikrotik\RecoverPppRouterJob;
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
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

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
        'status' => StatusLayanan::Proses,
    ]);
});

test('ProvisionPppoeAccountJob creates secret and logs success', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($l) => $l->id === $this->layanan->id)
        )
        ->andReturn(['status' => 'success', 'action' => 'created']);

    $job = new ProvisionPppoeAccountJob($this->layanan);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::ProvisionPppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success)
        ->and($this->layanan->fresh()->status)->toBe(StatusLayanan::Aktif);
});

test('EnablePppoeAccountJob enables secret and logs success', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('enablePppoeSecret')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($l) => $l->id === $this->layanan->id)
        )
        ->andReturn(true);

    $job = new EnablePppoeAccountJob($this->layanan);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::EnablePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('DisablePppoeAccountJob disables secret and disconnects active session', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('disablePppoeSecret')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($l) => $l->id === $this->layanan->id),
            true
        )
        ->andReturn(true);

    $job = new DisablePppoeAccountJob($this->layanan, true);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::DisablePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('SyncIpPoolToRouterJob syncs pool and logs success', function () {
    $pool = IpPool::factory()->create(['router_id' => $this->router->id]);

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('syncIpPool')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($p) => $p->id === $pool->id)
        )
        ->andReturn(['status' => 'success']);

    $job = new SyncIpPoolToRouterJob($pool);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('ip_pool_id', $pool->id)
        ->where('job_type', MikrotikJobType::SyncIpPool)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('PingRouterJob tests connection and logs success', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('testConnection')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            3
        )
        ->andReturn(['status' => 'success']);

    $job = new PingRouterJob($this->router);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('router_id', $this->router->id)
        ->where('job_type', MikrotikJobType::Ping)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('RecoverPppRouterJob runs autoRecoverPppSecrets on mikrotik-low queue', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('autoRecoverPppSecrets')
        ->once()
        ->with(Mockery::on(fn ($r) => $r->id === $this->router->id))
        ->andReturn([
            'profiles' => ['total' => 1, 'synced' => 1, 'errors' => []],
            'secrets' => ['total_checked' => 1, 'recovered' => 1, 'already_synced' => 0, 'disabled' => 0, 'duplicates_removed' => 0, 'errors' => []],
            'total_checked' => 1,
            'recovered' => 1,
            'already_synced' => 0,
            'disabled' => 0,
            'duplicates_removed' => 0,
            'errors' => [],
        ]);

    $job = new RecoverPppRouterJob($this->router);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('router_id', $this->router->id)
        ->where('job_type', MikrotikJobType::ReconcilePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('job failure sends database notification to super_admin and noc users', function () {
    Notification::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $nocUser = User::factory()->create();
    $nocUser->assignRole('noc');

    MikrotikJobLog::create([
        'router_id' => $this->router->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'job_type' => MikrotikJobType::ProvisionPppoe,
        'status' => MikrotikJobStatus::Pending,
    ]);

    $job = new ProvisionPppoeAccountJob($this->layanan);
    $job->failed(new Exception('Connection timeout'));

    Notification::assertSentTo([$superAdmin, $nocUser], MikrotikJobFailedNotification::class);
});

test('SyncBandwidthProfileToRoutersJob ensures profile on all online routers', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('ensurePppProfile')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($p) => $p->id === $this->profil->id)
        )
        ->andReturn($this->profil->nama_bandwidth);

    $job = new SyncBandwidthProfileToRoutersJob($this->profil);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('router_id', $this->router->id)
        ->where('job_type', MikrotikJobType::SyncProfilBandwidth)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});
