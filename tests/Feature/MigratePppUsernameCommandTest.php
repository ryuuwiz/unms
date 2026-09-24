<?php

use App\Console\Commands\MigratePppUsernameCommand;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikException;
use App\Services\Mikrotik\MikrotikService;
use App\Support\PppDeletionContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->pelanggan = Pelanggan::factory()->create(['no_reg' => 'BF2308202601']);
    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $this->router = Router::factory()->online()->create();

    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'user_lama_01',
    ]);
});

test('dry-run menampilkan preview tanpa mengubah database', function () {
    $mikrotik = Mockery::mock(MikrotikService::class);
    $mikrotik->shouldNotReceive('deletePppoeSecret');
    $mikrotik->shouldNotReceive('createOrUpdatePppoeSecret');
    $this->app->instance(MikrotikService::class, $mikrotik);

    $this->artisan(MigratePppUsernameCommand::class, ['--dry-run' => true])
        ->expectsOutputToContain('PREVIEW')
        ->assertExitCode(0);

    // Database tidak berubah
    expect($this->layanan->fresh()->ppp_username)->toBe('user_lama_01');
});

test('migrasi mengupdate ppp_username ke format baru dan provision sync ke router', function () {
    $mikrotik = Mockery::mock(MikrotikService::class);
    $mikrotik->shouldReceive('deletePppoeSecret')
        ->once()
        ->with(Mockery::type(Router::class), 'user_lama_01', Mockery::type(PppDeletionContext::class))
        ->andReturn(true);
    $mikrotik->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->andReturn(['status' => 'success', 'action' => 'created']);
    $this->app->instance(MikrotikService::class, $mikrotik);

    $this->artisan(MigratePppUsernameCommand::class)
        ->expectsConfirmation('Lanjutkan migrasi?', 'yes')
        ->assertExitCode(0);

    $username = $this->layanan->fresh()->ppp_username;
    expect($username)->toMatch('/^BF2308202601_[0-9]{5}$/');
    expect(LayananPelanggan::extractCounter($username))->toBeBetween(10000, 99999);
});

test('counter increment jika pelanggan sudah punya layanan dengan format baru', function () {
    // Layanan pertama sudah berformat baru — harus di-skip (tidak dimigrasi ulang)
    $this->layanan->update(['ppp_username' => 'BF2308202601_10001']);

    // Layanan kedua masih format lama — harus dimigrasi ke format baru
    $layanan2 = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'user_lama_02',
    ]);

    $mikrotik = Mockery::mock(MikrotikService::class);
    $mikrotik->shouldReceive('deletePppoeSecret')->once()->andReturn(true);
    $mikrotik->shouldReceive('createOrUpdatePppoeSecret')->once()->andReturn(['status' => 'success', 'action' => 'created']);
    $this->app->instance(MikrotikService::class, $mikrotik);

    $this->artisan(MigratePppUsernameCommand::class)
        ->expectsConfirmation('Lanjutkan migrasi?', 'yes')
        ->assertExitCode(0);

    // Layanan pertama SKIP: tetap tidak berubah
    expect($this->layanan->fresh()->ppp_username)->toBe('BF2308202601_10001');
    // Layanan kedua di-migrasi ke format baru (dan tidak tabrakan dengan layanan 1)
    $username2 = $layanan2->fresh()->ppp_username;
    expect($username2)->toMatch('/^BF2308202601_[0-9]{5}$/')
        ->and($username2)->not->toBe('BF2308202601_10001');
});

test('gagal pada satu layanan tidak menghentikan migrasi layanan lain', function () {
    $pelanggan2 = Pelanggan::factory()->create(['no_reg' => 'BF2308202602']);
    $layanan2 = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan2->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'ppp_username' => 'user_lama_02',
    ]);

    $callCount = 0;
    $mikrotik = Mockery::mock(MikrotikService::class);
    $mikrotik->shouldReceive('deletePppoeSecret')
        ->twice()
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new MikrotikException('Router timeout');
            }

            return true;
        });
    $mikrotik->shouldReceive('createOrUpdatePppoeSecret')
        ->once()
        ->andReturn(['status' => 'success', 'action' => 'created']);
    $this->app->instance(MikrotikService::class, $mikrotik);

    $this->artisan(MigratePppUsernameCommand::class)
        ->expectsConfirmation('Lanjutkan migrasi?', 'yes')
        ->assertExitCode(0);

    // Layanan yang gagal: ppp_username tidak berubah
    expect($this->layanan->fresh()->ppp_username)->toBe('user_lama_01');

    // Layanan yang berhasil: ppp_username sudah dimigrasi
    expect($layanan2->fresh()->ppp_username)->toMatch('/^BF2308202602_[0-9]{5}$/');

    // Log kegagalan tercatat
    expect(MikrotikJobLog::where('layanan_pelanggan_id', $this->layanan->id)->exists())->toBeTrue();
});
