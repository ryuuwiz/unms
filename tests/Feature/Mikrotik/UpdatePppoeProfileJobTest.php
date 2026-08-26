<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\UpdatePppoeProfileJob;
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
    $this->profilLama = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Home-10M']);
    $this->paketLama = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profilLama->id]);
    $this->profilBaru = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Home-50M']);
    $this->paketBaru = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profilBaru->id]);

    $this->layanan = LayananPelanggan::factory()->create([
        'router_id' => $this->router->id,
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paketBaru->id,
        'status' => StatusLayanan::Aktif,
        'nama_site' => 'Site Ruko Thamrin',
        'alamat_pemasangan' => 'Jl. MH Thamrin No. 12',
    ]);
});

test('UpdatePppoeProfileJob executes updatePppoeProfile and logs success', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('updatePppoeProfile')
        ->once()
        ->with(
            Mockery::on(fn ($r) => $r->id === $this->router->id),
            Mockery::on(fn ($l) => $l->id === $this->layanan->id),
            true
        )
        ->andReturn([
            'status' => 'success',
            'action' => 'profile_updated',
            'username' => $this->layanan->ppp_username,
            'profile' => 'Home-50M',
            'session_kicked' => true,
        ]);

    $job = new UpdatePppoeProfileJob($this->layanan, true);
    $job->handle($mockService);

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::UpdatePppoeProfile)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success)
        ->and($log->payload['username'])->toBe($this->layanan->ppp_username)
        ->and($log->payload['result']['profile'])->toBe('Home-50M');
});

test('UpdatePppoeProfileJob logs failure when exception occurs', function () {
    $mockService = Mockery::mock(MikrotikService::class);
    $mockService->shouldReceive('updatePppoeProfile')
        ->once()
        ->andThrow(new RuntimeException('Koneksi socket MikroTik terputus'));

    $job = new UpdatePppoeProfileJob($this->layanan);

    try {
        $job->handle($mockService);
    } catch (Throwable $e) {
        expect($e->getMessage())->toBe('Koneksi socket MikroTik terputus');
    }

    $log = MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)
        ->where('job_type', MikrotikJobType::UpdatePppoeProfile)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Failed)
        ->and($log->error_message)->toBe('Koneksi socket MikroTik terputus');
});

test('UpdatePppoeProfileJob failed() method notifies noc and super admin', function () {
    Notification::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $noc = User::factory()->create();
    $noc->assignRole('noc');

    $job = new UpdatePppoeProfileJob($this->layanan);

    MikrotikJobLog::create([
        'router_id' => $this->router->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'job_type' => MikrotikJobType::UpdatePppoeProfile,
        'status' => MikrotikJobStatus::Pending,
        'attempt_count' => 3,
    ]);

    $job->failed(new RuntimeException('Timeout'));

    Notification::assertSentTo([$superAdmin, $noc], MikrotikJobFailedNotification::class);
});
