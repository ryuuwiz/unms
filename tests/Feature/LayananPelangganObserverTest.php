<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\CleanupPppSecretOnOldRouterJob;
use App\Jobs\Mikrotik\UpdatePppoeProfileJob;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use App\Support\PppDeletionContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->routerLama = Router::factory()->online()->create();
    $this->routerBaru = Router::factory()->online()->create();
    $this->pelanggan = Pelanggan::factory()->create();
    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $this->poolLama = IpPool::factory()->create(['router_id' => $this->routerLama->id]);
    $this->poolBaru = IpPool::factory()->create(['router_id' => $this->routerBaru->id]);
    $this->layanan = LayananPelanggan::factory()->create([
        'router_id' => $this->routerLama->id,
        'ip_pool_id' => $this->poolLama->id,
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Aktif,
    ]);
});

// --- Observer: dispatch cleanup saat router berubah ---

test('Observer dispatch CleanupPppSecretOnOldRouterJob when router_id changes', function () {
    Queue::fake();

    $this->layanan->update([
        'router_id' => $this->routerBaru->id,
        'ip_pool_id' => $this->poolBaru->id,
    ]);

    Queue::assertPushed(CleanupPppSecretOnOldRouterJob::class, function ($job) {
        return $job->oldRouterId === $this->routerLama->id
            && $job->pppUsername === $this->layanan->ppp_username
            && $job->layananPelangganId === $this->layanan->id;
    });
});

test('Observer dispatch CleanupPppSecretOnOldRouterJob when ppp_username changes on same router', function () {
    Queue::fake();

    $oldUsername = $this->layanan->ppp_username;
    $newUsername = "{$this->pelanggan->no_reg}_00099";

    $this->layanan->update([
        'ppp_username' => $newUsername,
    ]);

    Queue::assertPushed(CleanupPppSecretOnOldRouterJob::class, function ($job) use ($oldUsername) {
        return $job->oldRouterId === $this->routerLama->id
            && $job->pppUsername === $oldUsername
            && $job->layananPelangganId === $this->layanan->id;
    });
});

test('Observer dispatch CleanupPppSecretOnOldRouterJob when LayananPelanggan is deleted', function () {
    Queue::fake();

    $username = $this->layanan->ppp_username;
    $routerId = $this->layanan->router_id;
    $layananId = $this->layanan->id;

    $this->layanan->delete();

    Queue::assertPushed(CleanupPppSecretOnOldRouterJob::class, function ($job) use ($username, $routerId, $layananId) {
        return $job->oldRouterId === $routerId
            && $job->pppUsername === $username
            && $job->layananPelangganId === $layananId;
    });
});

test('Observer does NOT dispatch cleanup when router_id does not change', function () {
    Queue::fake();

    $this->layanan->update([
        'ip_pool_id' => $this->poolLama->id,
    ]);

    Queue::assertNotPushed(CleanupPppSecretOnOldRouterJob::class);
});

test('Observer resets ip_pool_id to null when router_id changes', function () {
    Queue::fake();

    $this->layanan->update([
        'router_id' => $this->routerBaru->id,
    ]);

    expect($this->layanan->fresh()->ip_pool_id)->toBeNull();
});

test('Observer keeps ip_pool_id chosen in the same update that changes router_id', function () {
    Queue::fake();

    $this->layanan->update([
        'router_id' => $this->routerBaru->id,
        'ip_pool_id' => $this->poolBaru->id,
    ]);

    expect($this->layanan->fresh()->ip_pool_id)->toBe($this->poolBaru->id);
});

test('Observer dispatch UpdatePppoeProfileJob when paket_layanan_id changes', function () {
    Queue::fake();

    $paketBaru = PaketLayanan::factory()->create();

    $this->layanan->update([
        'paket_layanan_id' => $paketBaru->id,
    ]);

    Queue::assertPushed(UpdatePppoeProfileJob::class, function ($job) {
        return $job->layanan->id === $this->layanan->id;
    });
});

test('Observer does NOT dispatch UpdatePppoeProfileJob when paket_layanan_id is unchanged', function () {
    Queue::fake();

    $this->layanan->update([
        'nama_site' => 'Site Baru',
    ]);

    Queue::assertNotPushed(UpdatePppoeProfileJob::class);
});

// --- Model: resolveRemoteAddress ---

test('resolveRemoteAddress returns ip_static when present', function () {
    $this->layanan->ip_static = '192.168.1.100';
    $this->layanan->ip_pool_id = $this->poolLama->id;

    expect($this->layanan->resolveRemoteAddress())->toBe('192.168.1.100');
});

test('resolveRemoteAddress is null for dynamic PPPoE even with a pool -- RouterOS allocates from the profile pool', function () {
    $this->layanan->ip_static = null;
    $this->layanan->ip_pool_id = $this->poolLama->id;
    $this->layanan->ip_dynamic = '10.0.0.2';
    $this->layanan->setRelation('ipPool', $this->poolLama);

    expect($this->layanan->resolveRemoteAddress())->toBeNull()
        ->and($this->layanan->usesLiteralAddress())->toBeFalse()
        ->and($this->layanan->profilePool()?->id)->toBe($this->poolLama->id);
});

test('resolveRemoteAddress returns null when neither ip_static nor a pool is set', function () {
    $this->layanan->ip_static = null;
    $this->layanan->ip_pool_id = null;
    $this->layanan->ip_dynamic = null;
    $this->layanan->setRelation('ipPool', null);

    expect($this->layanan->resolveRemoteAddress())->toBeNull();
});

// --- Model: resolveLocalAddress & IpPool getGatewayAddress ---

test('IpPool getGatewayAddress returns first host of ip_network', function () {
    $this->poolLama->ip_network = '10.0.0.0';
    expect($this->poolLama->getGatewayAddress())->toBe('10.0.0.1');

    $this->poolLama->ip_network = '10.0.1.0';
    expect($this->poolLama->getGatewayAddress())->toBe('10.0.1.1');
});

test('resolveLocalAddress is null for dynamic PPPoE (gateway lives on the profile)', function () {
    $this->poolLama->ip_network = '10.0.0.0';
    $this->layanan->ip_static = null;
    $this->layanan->ip_pool_id = $this->poolLama->id;
    $this->layanan->setRelation('ipPool', $this->poolLama);

    expect($this->layanan->resolveLocalAddress())->toBeNull();
});

test('resolveLocalAddress returns gateway of related ipPool for a literal ip_static', function () {
    $this->poolLama->ip_network = '10.0.0.0';
    $this->layanan->ip_static = '10.0.0.50';
    $this->layanan->ip_pool_id = $this->poolLama->id;
    $this->layanan->setRelation('ipPool', $this->poolLama);

    expect($this->layanan->resolveLocalAddress())->toBe('10.0.0.1');
});

test('resolveLocalAddress calculates subnet gateway when ip_static is present without ipPool', function () {
    $this->layanan->ip_pool_id = null;
    $this->layanan->setRelation('ipPool', null);
    $this->layanan->ip_static = '10.0.1.25';

    expect($this->layanan->resolveLocalAddress())->toBe('10.0.1.1');
});

test('resolveLocalAddress returns null when neither ipPool nor ip_static exists', function () {
    $this->layanan->ip_pool_id = null;
    $this->layanan->setRelation('ipPool', null);
    $this->layanan->ip_static = null;

    expect($this->layanan->resolveLocalAddress())->toBeNull();
});

// --- Job: CleanupPppSecretOnOldRouterJob ---

test('CleanupPppSecretOnOldRouterJob calls deletePppoeSecret and logs Success', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('deletePppoeSecret')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->routerLama->id),
            $this->layanan->ppp_username,
            Mockery::type(PppDeletionContext::class)
        )
        ->andReturn(true);

    $job = new CleanupPppSecretOnOldRouterJob(
        $this->routerLama->id,
        $this->layanan->ppp_username,
        $this->layanan->id
    );
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::DeletePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('CleanupPppSecretOnOldRouterJob logs Failed when deletePppoeSecret throws', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('deletePppoeSecret')
        ->once()
        ->andThrow(new RuntimeException('Connection refused'));

    $job = new CleanupPppSecretOnOldRouterJob(
        $this->routerLama->id,
        $this->layanan->ppp_username,
        $this->layanan->id
    );

    // Tidak boleh melempar exception — dicatat sebagai Failed
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::DeletePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Failed)
        ->and($log->error_message)->toContain('Connection refused');
});
