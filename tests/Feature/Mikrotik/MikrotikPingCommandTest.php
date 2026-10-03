<?php

use App\Enums\StatusLayanan;
use App\Enums\StatusRouter;
use App\Jobs\Mikrotik\DisablePppoeAccountJob;
use App\Jobs\Mikrotik\PingRouterJob;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('mikrotik:ping command dispatches PingRouterJob for routers', function () {
    Queue::fake([PingRouterJob::class]);

    $router1 = Router::factory()->online()->create();
    $router2 = Router::factory()->online()->create();

    $this->artisan('mikrotik:ping')
        ->assertSuccessful();

    Queue::assertPushed(PingRouterJob::class, 2);
});

test('mikrotik:ping melewati router Online yang di-ping kurang dari 2 jam lalu', function () {
    Queue::fake([PingRouterJob::class]);

    Router::factory()->online()->create(['last_ping_at' => now()->subHour()]);
    $online = Router::factory()->online()->create(['last_ping_at' => now()->subHours(3)]);
    $offline = Router::factory()->create(['status_koneksi' => StatusRouter::Offline, 'last_ping_at' => now()]);

    $this->artisan('mikrotik:ping')->assertSuccessful();

    Queue::assertPushed(PingRouterJob::class, 2);
    Queue::assertPushed(PingRouterJob::class, fn ($job) => $job->router->is($online));
    Queue::assertPushed(PingRouterJob::class, fn ($job) => $job->router->is($offline));
});

test('layanan:cek-isolir command suspends expired services', function () {
    Queue::fake([DisablePppoeAccountJob::class]);

    $router = Router::factory()->online()->create();
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create();
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    $expiredLayanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => Carbon::yesterday(),
    ]);

    $activeLayanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => Carbon::tomorrow(),
    ]);

    $this->artisan('layanan:cek-isolir')
        ->assertSuccessful();

    expect($expiredLayanan->fresh()->status)->toBe(StatusLayanan::Suspend)
        ->and($activeLayanan->fresh()->status)->toBe(StatusLayanan::Aktif);

    Queue::assertPushed(DisablePppoeAccountJob::class, function ($job) use ($expiredLayanan) {
        return $job->layanan->id === $expiredLayanan->id;
    });
});
