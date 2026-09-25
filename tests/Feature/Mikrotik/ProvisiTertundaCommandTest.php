<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Exceptions\MikrotikException;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Models\MikrotikJobLog;
use App\Models\User;
use App\Notifications\MikrotikJobNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Provisi Cadangan (CONTEXT.md): penjadwal mengambil alih layanan yang tidak diprovisi job antrean.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake([ProvisionPppoeAccountJob::class]);
    [$this->router, $this->layanan] = layananPppoeDinamis();
    $this->layanan->updateQuietly(['status' => StatusLayanan::Aktif, 'provisioning_status' => ProvisioningStatus::Failed, 'last_provisioning_error' => 'Connection timed out']);
});

test('mengantrekan ulang layanan yang belum terprovisi dan tidak sedang dicoba', function () {
    $this->artisan('mikrotik:provisi-tertunda')->assertSuccessful();

    Queue::assertPushed(ProvisionPppoeAccountJob::class, fn (ProvisionPppoeAccountJob $job) => $job->layanan->is($this->layanan)
        && $job->galatSebelumnya === 'Connection timed out');
});

test('melewati layanan yang baru dicoba, sudah terprovisi, berhenti, atau routernya offline', function (Closure $ubah) {
    $ubah->call($this);

    $this->artisan('mikrotik:provisi-tertunda')->assertSuccessful();

    Queue::assertNotPushed(ProvisionPppoeAccountJob::class);
})->with([
    'baru dicoba job antrean' => [fn () => MikrotikJobLog::create([
        'router_id' => $this->router->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'job_type' => MikrotikJobType::ProvisionPppoe,
        'status' => MikrotikJobStatus::Pending,
        'attempt_count' => 1,
    ])],
    'sudah terprovisi' => [fn () => $this->layanan->updateQuietly(['provisioning_status' => ProvisioningStatus::Success])],
    'layanan berhenti' => [fn () => $this->layanan->updateQuietly(['status' => StatusLayanan::Berhenti])],
    'router offline' => [fn () => $this->router->update(['status_koneksi' => StatusRouter::Offline])],
]);

test('galat yang sama dengan percobaan sebelumnya tidak diberitahukan ulang; galat baru tetap diberitahukan', function () {
    Notification::fake();
    $noc = User::factory()->create();
    $noc->assignRole('noc');

    (new ProvisionPppoeAccountJob($this->layanan, galatSebelumnya: 'Connection timed out'))
        ->failed(new MikrotikException('Gagal provisi PPPoE x pada router y: Connection timed out'));
    Notification::assertNothingSent();

    // Percobaan cadangan berikutnya paling cepat 5 menit kemudian.
    $this->travel(5)->minutes();
    (new ProvisionPppoeAccountJob($this->layanan, galatSebelumnya: 'Connection timed out'))
        ->failed(new MikrotikException("Secret sudah ada tanpa komentar 'UNMS:'"));
    Notification::assertSentToTimes($noc, MikrotikJobNotification::class, 1);

    // Kegagalan tetap tercatat di Job Log walau tidak diberitahukan.
    expect(MikrotikJobLog::where('status', MikrotikJobStatus::Failed)->count())->toBe(2);
});
