<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Exceptions\MikrotikConnectionException;
use App\Exceptions\MikrotikException;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Mikrotik\PingRouterJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Jobs\Mikrotik\RecoverPppRouterJob;
use App\Jobs\Mikrotik\SyncBandwidthProfileToRoutersJob;
use App\Jobs\Mikrotik\SyncIpPoolToRouterJob;
use App\Models\AntrianWaBlast;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\RouterPaket;
use App\Models\User;
use App\Notifications\MikrotikJobNotification;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Mikrotik\NotifikasiNoc;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

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

test('ProvisionPppoeAccountJob does not retry and notifies immediately on permanent config error (e.g. IP Pool on wrong router)', function () {
    Notification::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->andThrow(new MikrotikException("Layanan {$this->layanan->ppp_username} memiliki IP Pool 'POOL-X' yang terdaftar pada router lain, bukan {$this->router->nama_router}. Perbaiki alokasi IP Pool layanan sebelum provisi."));

    $job = new ProvisionPppoeAccountJob($this->layanan);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::ProvisionPppoe)
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Failed)
        ->and($log->error_message)->toContain('terdaftar pada router lain');

    Notification::assertSentTo($superAdmin, MikrotikJobNotification::class);
});

test('ProvisionPppoeAccountJob rethrows MikrotikConnectionException so the queue retries it', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->andThrow(new MikrotikConnectionException('Router tidak dapat dihubungi.'));

    $job = new ProvisionPppoeAccountJob($this->layanan);

    expect(fn () => $job->handle($mockService))
        ->toThrow(MikrotikConnectionException::class, 'Router tidak dapat dihubungi.');

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::ProvisionPppoe)
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Failed);
});

test('EnablePppoeAccountJob enables secret and logs success', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('bukaIsolirPppoeSecret')
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
    $mockService->shouldReceive('isolirPppoeSecret')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($l) => $l->id === $this->layanan->id)
        )
        ->andReturn(true);

    $job = new DisablePppoeAccountJob($this->layanan);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::DisablePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('EnablePppoeAccountJob skips execution and logs Dilewati when router is known offline', function () {
    $this->router->update(['status_koneksi' => StatusRouter::Offline]);
    $this->layanan->refresh();

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldNotReceive('bukaIsolirPppoeSecret');

    $job = new EnablePppoeAccountJob($this->layanan);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::EnablePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Dilewati);
});

test('DisablePppoeAccountJob skips execution and logs Dilewati when router is known offline', function () {
    $this->router->update(['status_koneksi' => StatusRouter::Offline]);
    $this->layanan->refresh();

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldNotReceive('isolirPppoeSecret');

    $job = new DisablePppoeAccountJob($this->layanan);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::DisablePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Dilewati);
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
    $mockService->shouldReceive('syncPaketProfilesUsingPool')->once()->andReturn(['total' => 0, 'synced' => 0, 'errors' => []]);

    $job = new SyncIpPoolToRouterJob($pool);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('ip_pool_id', $pool->id)
        ->where('job_type', MikrotikJobType::SyncIpPool)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

/**
 * Ping Router (CONTEXT.md "Router Offline"): MikrotikService::pingRouter() dipalsukan; hasil true = terjangkau.
 */
function jalankanPing(Router $router, bool $terjangkau, int $percobaan = 1): PingRouterJob
{
    $mikrotik = Mockery::mock(MikrotikService::class);
    $mikrotik->shouldReceive('pingRouter')->once()->andReturnUsing(function (Router $router) use ($terjangkau) {
        if ($terjangkau) {
            $router->update(['status_koneksi' => StatusRouter::Online]);
        } else {
            $router->update(['last_ping_message' => 'Connection refused']);
        }

        return $terjangkau;
    });

    $job = (new PingRouterJob($router))->withFakeQueueInteractions();
    $job->job->attempts = $percobaan;
    $job->handle($mikrotik, app(NotifikasiNoc::class));

    return $job;
}

function jumlahLogPing(): int
{
    return MikrotikJobLog::where('job_type', MikrotikJobType::Ping)->count();
}

test('PingRouterJob: router Online yang gagal di bawah 10 percobaan tetap Online dan dicoba ulang 30 detik lagi', function () {
    Notification::fake();

    jalankanPing($this->router, terjangkau: false, percobaan: 9)->assertReleased(PingRouterJob::JEDA_PERCOBAAN_DETIK);

    expect($this->router->fresh()->status_koneksi)->toBe(StatusRouter::Online)
        ->and(jumlahLogPing())->toBe(0);
    Notification::assertNothingSent();
});

test('PingRouterJob: router Online yang gagal 10x menjadi Router Offline dengan satu notifikasi browser, tanpa WhatsApp', function () {
    Notification::fake();
    $noc = User::factory()->create(['phone' => '081234567890']);
    $noc->assignRole('noc');
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    jalankanPing($this->router, terjangkau: false, percobaan: 10)->assertNotReleased();

    expect($this->router->fresh()->status_koneksi)->toBe(StatusRouter::Offline)
        ->and(MikrotikJobLog::where('job_type', MikrotikJobType::Ping)->sole()->status)->toBe(MikrotikJobStatus::Failed);
    Notification::assertSentToTimes($noc, MikrotikJobNotification::class, 1);
    Notification::assertSentToTimes($superAdmin, MikrotikJobNotification::class, 1);
    // Router mati tidak lewat WA -- lihat CONTEXT.md "Notifikasi NOC".
    expect(AntrianWaBlast::where('jenis', "noc_mikrotik_u{$noc->id}")->count())->toBe(0);
});

test('PingRouterJob: percobaan yang berhasil menghentikan putaran tanpa log, notifikasi, atau pemulihan', function () {
    Notification::fake();
    Queue::fake([RecoverPppRouterJob::class]);

    jalankanPing($this->router, terjangkau: true, percobaan: 4)->assertNotReleased();

    expect($this->router->fresh()->status_koneksi)->toBe(StatusRouter::Online)
        ->and(jumlahLogPing())->toBe(0);
    Notification::assertNothingSent();
    Queue::assertNotPushed(RecoverPppRouterJob::class);
});

test('PingRouterJob: router Tidak Diketahui yang gagal 10x menjadi Router Offline dan NOC diberi tahu', function () {
    Notification::fake();
    $noc = User::factory()->create();
    $noc->assignRole('noc');
    $this->router->update(['status_koneksi' => StatusRouter::Unknown]);

    jalankanPing($this->router, terjangkau: false, percobaan: 10);

    expect($this->router->fresh()->status_koneksi)->toBe(StatusRouter::Offline)
        ->and(jumlahLogPing())->toBe(1);
    Notification::assertSentToTimes($noc, MikrotikJobNotification::class, 1);
});

test('PingRouterJob: router Tidak Diketahui yang terjangkau menjadi Online tanpa log Ping dan tanpa notifikasi', function () {
    Notification::fake();
    Queue::fake([RecoverPppRouterJob::class]);
    $this->router->update(['status_koneksi' => StatusRouter::Unknown]);

    jalankanPing($this->router, terjangkau: true);

    expect($this->router->fresh()->status_koneksi)->toBe(StatusRouter::Online)
        ->and(jumlahLogPing())->toBe(0);
    Notification::assertNothingSent();
});

test('PingRouterJob: router Offline yang terjangkau kembali Online dan memicu pemulihan, tanpa notifikasi', function () {
    Notification::fake();
    Queue::fake([RecoverPppRouterJob::class]);
    $this->router->update(['status_koneksi' => StatusRouter::Offline]);

    jalankanPing($this->router, terjangkau: true);

    expect($this->router->fresh()->status_koneksi)->toBe(StatusRouter::Online)
        ->and(jumlahLogPing())->toBe(0);
    Notification::assertNothingSent();
    Queue::assertPushed(RecoverPppRouterJob::class, 1);
});

test('PingRouterJob: router Offline yang gagal lagi cukup satu percobaan, tanpa log dan tanpa notifikasi baru', function () {
    Notification::fake();
    $this->router->update(['status_koneksi' => StatusRouter::Offline]);

    jalankanPing($this->router, terjangkau: false)->assertNotReleased();

    expect($this->router->fresh()->status_koneksi)->toBe(StatusRouter::Offline)
        ->and(jumlahLogPing())->toBe(0);
    Notification::assertNothingSent();
});

test('PingRouterJob: pemicu kedua untuk router yang sama tidak membuka putaran baru selama putaran berjalan', function () {
    Queue::fake([PingRouterJob::class]);

    PingRouterJob::dispatch($this->router);
    PingRouterJob::dispatch($this->router);

    Queue::assertPushed(PingRouterJob::class, 1);
});

test('RecoverPppRouterJob runs autoRecoverPppSecrets on mikrotik-low queue', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('autoRecoverPppSecrets')
        ->once()
        ->with(Mockery::on(fn ($r) => $r->id === $this->router->id), Mockery::any(), Mockery::any())
        ->andReturn([
            'profiles' => ['total' => 1, 'synced' => 1, 'errors' => []],
            'secrets' => ['total_checked' => 1, 'recovered' => 1, 'already_synced' => 0, 'disabled' => 0, 'duplicates_removed' => 0, 'errors' => []],
            'total_checked' => 1,
            'recovered' => 1,
            'already_synced' => 0,
            'disabled' => 0,
            'duplicates_removed' => 0,
            'delete_cap_exceeded' => false,
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

    Notification::assertSentTo([$superAdmin, $nocUser], MikrotikJobNotification::class);
});

test('SyncBandwidthProfileToRoutersJob ensures the paket profile on routers selling a paket with that bandwidth', function () {
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $routerPaket = RouterPaket::create(['paket_layanan_id' => $paket->id, 'router_id' => $this->router->id, 'ip_pool_id' => IpPool::factory()->create(['router_id' => $this->router->id])->id]);

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('ensurePaketProfile')
        ->once()
        ->with(Mockery::on(fn ($rp) => $rp->is($routerPaket)))
        ->andReturn($paket->nama_paket);

    $job = new SyncBandwidthProfileToRoutersJob($this->profil);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('router_id', $this->router->id)
        ->where('job_type', MikrotikJobType::SyncProfilBandwidth)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('SyncBandwidthProfileToRoutersJob skips a router already locked by another Mikrotik job', function () {
    // Root-cause coverage: this job used to hit RouterOS with zero locking, so it
    // could open a second concurrent socket while RecoverPppRouterJob/ProvisionRouterJob
    // already held the router's ADR-0032 lock -- contending for the same router's CPU.
    $lock = Cache::lock("mikrotik:router:{$this->router->id}", 120);
    expect($lock->get())->toBeTrue();

    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    RouterPaket::create(['paket_layanan_id' => $paket->id, 'router_id' => $this->router->id, 'ip_pool_id' => IpPool::factory()->create(['router_id' => $this->router->id])->id]);

    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldNotReceive('ensurePaketProfile');

    $job = new SyncBandwidthProfileToRoutersJob($this->profil);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('router_id', $this->router->id)
        ->where('job_type', MikrotikJobType::SyncProfilBandwidth)
        ->first();

    expect($log)->toBeNull(); // never attempted: skipped before creating a job log

    $lock->release();
});
