<?php

use App\Actions\LayananPelanggan\UbahStatusLayananAction;
use App\Enums\JenisKoneksi;
use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Exceptions\MikrotikException;
use App\Jobs\Mikrotik\CleanupPppSecretOnOldRouterJob;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Jobs\Mikrotik\EnablePppoeAccountJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Jobs\Mikrotik\RecoverPppRouterJob;
use App\Jobs\Mikrotik\RemoveIpPoolFromRouterJob;
use App\Livewire\IpPool\Create as PoolCreate;
use App\Livewire\IpPool\Edit as PoolEdit;
use App\Livewire\IpPool\Index as PoolIndex;
use App\Livewire\LayananPelanggan\Edit as LayananEdit;
use App\Livewire\LayananPelanggan\Index as LayananIndex;
use App\Livewire\Router\Index as RouterIndex;
use App\Models\IpPool;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Models\User;
use App\Notifications\MikrotikJobFailedNotification;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

// --- Aturan 2: isolir tidak menghapus, Berhenti (input admin/NOC) menghapus ---

test('Berhenti menghapus secret dengan jejak actor dan alasan, dan tidak lagi hanya men-disable', function () {
    [$router, $layanan] = layananPppoeDinamis();

    app(UbahStatusLayananAction::class)->execute($layanan, StatusLayanan::Berhenti, $this->admin, 'Pencabutan selesai (tiket T-1)');

    Queue::assertPushed(CleanupPppSecretOnOldRouterJob::class, fn ($job) => $job->pppUsername === $layanan->ppp_username
        && $job->actor === $this->admin->id
        && str_contains($job->reason, 'Pencabutan selesai (tiket T-1)'));
    Queue::assertNotPushed(DisablePppoeAccountJob::class);
});

test('isolir (Suspend) hanya men-disable dan tidak pernah menghapus secret', function () {
    [, $layanan] = layananPppoeDinamis();

    app(UbahStatusLayananAction::class)->execute($layanan, StatusLayanan::Suspend, null, 'Isolir otomatis');

    Queue::assertPushed(DisablePppoeAccountJob::class);
    Queue::assertNotPushed(CleanupPppSecretOnOldRouterJob::class);
});

test('job Disable yang basi tidak mengisolir layanan yang sudah Aktif kembali', function () {
    [$router, $layanan] = layananPppoeDinamis();
    $layanan->update(['status' => StatusLayanan::Aktif]);
    $service = Mockery::mock(MikrotikService::class);
    $service->shouldNotReceive('disablePppoeSecret');

    (new DisablePppoeAccountJob($layanan->fresh()))->handle($service);

    expect(MikrotikJobLog::where('job_type', MikrotikJobType::DisablePppoe)->first()->status)->toBe(MikrotikJobStatus::Dilewati);
});

test('job Enable yang basi tidak membuka layanan yang sudah Suspend atau Berhenti lagi', function (StatusLayanan $status) {
    [, $layanan] = layananPppoeDinamis();
    $layanan->updateQuietly(['status' => $status]);
    $service = Mockery::mock(MikrotikService::class);
    $service->shouldNotReceive('enablePppoeSecret');

    (new EnablePppoeAccountJob($layanan->fresh()))->handle($service);

    expect(MikrotikJobLog::where('job_type', MikrotikJobType::EnablePppoe)->first()->status)->toBe(MikrotikJobStatus::Dilewati);
})->with([StatusLayanan::Suspend, StatusLayanan::Berhenti]);

// --- Form Edit tidak boleh melewati Action status ---

test('mengubah status lewat form Edit memicu event yang sama (Berhenti menghapus secret)', function () {
    [, $layanan] = layananPppoeDinamis();

    Livewire::actingAs($this->admin)
        ->test(LayananEdit::class, ['layananPelanggan' => $layanan])
        ->set('status', 'berhenti')
        ->call('save')
        ->assertHasNoErrors();

    expect($layanan->fresh()->status)->toBe(StatusLayanan::Berhenti);
    Queue::assertPushed(CleanupPppSecretOnOldRouterJob::class, fn ($job) => $job->actor === $this->admin->id);
});

test('form Edit menolak status di luar enum dan ip_static yang duplikat', function () {
    [$router, $layanan] = layananPppoeDinamis();

    Livewire::actingAs($this->admin)
        ->test(LayananEdit::class, ['layananPelanggan' => $layanan])
        ->set('status', 'ngawur')
        ->call('save')
        ->assertHasErrors('status');

    LayananPelanggan::factory()->create(['router_id' => $router->id, 'ip_static' => '10.9.9.9', 'jenis_koneksi' => JenisKoneksi::IpStatic]);
    $statis = LayananPelanggan::factory()->create(['router_id' => $router->id, 'jenis_koneksi' => JenisKoneksi::IpStatic]);

    Livewire::actingAs($this->admin)
        ->test(LayananEdit::class, ['layananPelanggan' => $statis])
        ->set('ip_static', '10.9.9.9')
        ->call('save')
        ->assertHasErrors('ip_static');
});

// --- Q7: provisi ulang saat username/router/pool/ip_static berubah ---

test('ganti username layanan aktif memprovisi ulang dengan kick, layanan Proses atau tanpa pool tidak', function () {
    [, $layanan] = layananPppoeDinamis();
    $layanan->update(['status' => StatusLayanan::Aktif]);
    Queue::fake();

    $layanan->update(['ppp_username' => $layanan->pelanggan->no_reg.'_54321']);
    Queue::assertPushed(ProvisionPppoeAccountJob::class, fn ($job) => $job->kickActive === true);
    Queue::assertPushed(CleanupPppSecretOnOldRouterJob::class);

    Queue::fake();
    $layanan->updateQuietly(['status' => StatusLayanan::Proses]);
    $layanan->update(['ppp_username' => $layanan->pelanggan->no_reg.'_65432']);
    Queue::assertNotPushed(ProvisionPppoeAccountJob::class);

    Queue::fake();
    $layanan->updateQuietly(['status' => StatusLayanan::Aktif]);
    $layanan->update(['ip_pool_id' => null]);
    Queue::assertNotPushed(ProvisionPppoeAccountJob::class);
});

// --- Q13: rekonsiliasi terjadwal ---

test('penjadwal malam hanya mengaudit orphan dan tidak pernah memakai --clean-orphans', function () {
    Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($output)->toContain('--audit-orphans')
        ->and($output)->not->toContain('--clean-orphans');
});

test('rekonsiliasi dilewati untuk router offline, dan mode audit tidak menghapus orphan', function () {
    $offline = Router::factory()->create(['status_koneksi' => 'offline']);
    $service = Mockery::mock(MikrotikService::class);
    $service->shouldNotReceive('autoRecoverPppSecrets');

    (new RecoverPppRouterJob($offline))->handle($service);

    expect(MikrotikJobLog::where('router_id', $offline->id)->first()->status)->toBe(MikrotikJobStatus::Dilewati);

    $online = Router::factory()->online()->create();
    $service = Mockery::mock(MikrotikService::class);
    $service->shouldReceive('autoRecoverPppSecrets')->once()->andReturn(['recovered' => 0, 'disabled' => 0, 'duplicates_removed' => 0, 'delete_cap_exceeded' => false, 'errors' => []]);
    $service->shouldReceive('cleanOrphanedPppSecrets')->once()->with(Mockery::any(), false)->andReturn(['orphans_count' => 2, 'orphans' => ['a', 'b'], 'errors' => []]);

    (new RecoverPppRouterJob($online, false, false, false, true))->handle($service);

    expect(MikrotikJobLog::where('router_id', $online->id)->first()->payload['orphans']['orphans_count'])->toBe(2);
});

test('batas hapus tercapai memberi tahu NOC hanya sekali per router per jam', function () {
    Notification::fake();
    $noc = User::factory()->create();
    $noc->assignRole('noc');
    $router = Router::factory()->online()->create();

    $service = Mockery::mock(MikrotikService::class);
    $service->shouldReceive('autoRecoverPppSecrets')->twice()->andReturn([
        'recovered' => 0, 'disabled' => 0, 'duplicates_removed' => 0, 'delete_cap_exceeded' => true,
        'errors' => ['Batas penghapusan 10 secret per eksekusi tercapai'],
    ]);

    (new RecoverPppRouterJob($router))->handle($service);
    (new RecoverPppRouterJob($router))->handle($service);

    Notification::assertSentToTimes($noc, MikrotikJobFailedNotification::class, 1);
    expect(MikrotikJobLog::where('router_id', $router->id)->where('status', MikrotikJobStatus::Failed)->count())->toBe(2);
});

// --- Q5/Q6: IP Pool ---

test('rentang pool tidak boleh memuat gateway (network + 1) dan ip_network harus alamat network', function () {
    $router = Router::factory()->create();

    Livewire::actingAs($this->admin)->test(PoolCreate::class)
        ->set('nama_pool', 'P1')->set('router_id', $router->id)
        ->set('ip_network', '10.0.0.0')->set('cidr', 24)
        ->set('rentang_ip_awal', '10.0.0.1')->set('rentang_ip_akhir', '10.0.0.254')
        ->call('save')
        ->assertHasErrors('rentang_ip_akhir');

    Livewire::actingAs($this->admin)->test(PoolCreate::class)
        ->set('nama_pool', 'P2')->set('router_id', $router->id)
        ->set('ip_network', '10.0.0.5')->set('cidr', 24)
        ->set('rentang_ip_awal', '10.0.0.10')->set('rentang_ip_akhir', '10.0.0.254')
        ->call('save')
        ->assertHasErrors('ip_network');

    expect(IpPool::count())->toBe(0);
});

test('nama dan network pool yang sedang dipakai layanan tidak dapat diubah', function () {
    [, $layanan] = layananPppoeDinamis();
    $pool = $layanan->ipPool;

    Livewire::actingAs($this->admin)->test(PoolEdit::class, ['pool' => $pool])
        ->set('nama_pool', 'Nama-Baru')
        ->call('save');

    expect($pool->fresh()->nama_pool)->toBe('Pool-Rumah');
});

test('menghapus pool tak terpakai membersihkan router, memindahkannya membersihkan router lama', function () {
    $router = Router::factory()->online()->create();
    $lain = Router::factory()->online()->create();
    $pool = IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Lama']);

    $pool->update(['router_id' => $lain->id]);
    Queue::assertPushed(RemoveIpPoolFromRouterJob::class, fn ($job) => $job->routerId === $router->id && $job->poolName === 'Pool-Lama');

    Livewire::actingAs($this->admin)->test(PoolIndex::class)
        ->call('confirmDelete', $pool->id)
        ->call('deleteIpPool');

    Queue::assertPushed(RemoveIpPoolFromRouterJob::class, fn ($job) => $job->routerId === $lain->id && $job->actor === $this->admin->id);
});

// --- Q8: hapus router ---

test('router dengan IP Publik terpasang tidak dapat dihapus', function () {
    $router = Router::factory()->create();
    $layanan = LayananPelanggan::factory()->create(['router_id' => $router->id]);
    IpPublik::factory()->create(['router_id' => $router->id, 'layanan_pelanggan_id' => $layanan->id, 'harga_ditagih' => 50000]);

    Livewire::actingAs($this->admin)->test(RouterIndex::class)
        ->call('confirmDelete', $router->id)
        ->call('deleteRouter');

    expect(Router::find($router->id))->not->toBeNull()
        ->and(IpPublik::count())->toBe(1);
});

test('memindahkan layanan PPPoE ke router lain mewajibkan pool tujuan lalu memprovisi ulang layanan aktif', function () {
    $lama = Router::factory()->online()->create();
    $tujuan = Router::factory()->online()->create();
    $pool = IpPool::factory()->create(['router_id' => $tujuan->id]);
    $layanan = LayananPelanggan::factory()->create(['router_id' => $lama->id, 'status' => StatusLayanan::Aktif]);

    $komponen = Livewire::actingAs($this->admin)->test(RouterIndex::class)
        ->call('confirmDelete', $lama->id)
        ->set('targetRouterId', $tujuan->id)
        ->call('deleteRouter');

    expect(Router::find($lama->id))->not->toBeNull()
        ->and($layanan->fresh()->router_id)->toBe($lama->id);

    $komponen->set('targetPoolId', $pool->id)->call('deleteRouter');

    expect(Router::find($lama->id))->toBeNull()
        ->and($layanan->fresh()->router_id)->toBe($tujuan->id)
        ->and($layanan->fresh()->ip_pool_id)->toBe($pool->id);
    Queue::assertPushed(ProvisionPppoeAccountJob::class, fn ($job) => $job->layanan->is($layanan));
});

// --- F13: provisi massal lewat antrean, Berhenti tidak pernah diprovisi ---

test('provisi massal hanya mengantrekan job untuk layanan Aktif/Suspend yang belum terprovisi, tidak memanggil router dari request', function () {
    $router = Router::factory()->online()->create();
    $aktif = LayananPelanggan::factory()->create(['router_id' => $router->id, 'status' => StatusLayanan::Aktif, 'provisioning_status' => 'pending']);
    $suspend = LayananPelanggan::factory()->create(['router_id' => $router->id, 'status' => StatusLayanan::Suspend, 'provisioning_status' => 'pending']);
    $berhenti = LayananPelanggan::factory()->create(['router_id' => $router->id, 'status' => StatusLayanan::Berhenti, 'provisioning_status' => 'pending']);
    $sudah = LayananPelanggan::factory()->create(['router_id' => $router->id, 'status' => StatusLayanan::Aktif, 'provisioning_status' => 'success']);

    Livewire::actingAs($this->admin)->test(LayananIndex::class)->call('provisionAllPending');

    Queue::assertPushed(ProvisionPppoeAccountJob::class, 2);
    Queue::assertPushed(ProvisionPppoeAccountJob::class, fn ($job) => $job->layanan->is($aktif));
    Queue::assertPushed(ProvisionPppoeAccountJob::class, fn ($job) => $job->layanan->is($suspend));
    Queue::assertNotPushed(ProvisionPppoeAccountJob::class, fn ($job) => $job->layanan->is($berhenti) || $job->layanan->is($sudah));
});

test('createOrUpdatePppoeSecret menolak layanan Berhenti sehingga secret yang sengaja dihapus tidak muncul lagi', function () {
    [$router, $layanan] = layananPppoeDinamis();
    $layanan->updateQuietly(['status' => StatusLayanan::Berhenti]);
    [$client, $sent] = fakeRouterOs();

    expect(fn () => (new MikrotikService)->createOrUpdatePppoeSecret($router, $layanan->fresh(), $client))
        ->toThrow(MikrotikException::class, 'Berhenti')
        ->and($sent)->toHaveCount(0);
});
