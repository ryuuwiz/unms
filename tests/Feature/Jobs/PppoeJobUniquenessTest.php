<?php

use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('duplicate PPPoE jobs with the same unique ID are queued only once', function () {
    $router = Router::factory()->create();
    $pelanggan = Pelanggan::factory()->create();
    $paket = PaketLayanan::factory()->create();
    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
    ]);

    Queue::fake();

    ProvisionPppoeAccountJob::dispatch($layanan);
    ProvisionPppoeAccountJob::dispatch($layanan);

    Queue::assertPushed(ProvisionPppoeAccountJob::class, 1);
});
