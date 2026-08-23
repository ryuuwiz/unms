<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Livewire\Mikrotik\LogIndex;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->nocUser = User::factory()->create();
    $this->nocUser->assignRole('noc');

    $this->salesUser = User::factory()->create();
    $this->salesUser->assignRole('sales');

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

test('super admin and noc can access mikrotik logs page', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('mikrotik.logs.index'))
        ->assertOk()
        ->assertSee('Log Integrasi MikroTik');

    $this->actingAs($this->nocUser)
        ->get(route('mikrotik.logs.index'))
        ->assertOk();
});

test('unauthorized user cannot access mikrotik logs page', function () {
    $this->actingAs($this->salesUser)
        ->get(route('mikrotik.logs.index'))
        ->assertForbidden();
});

test('can list and filter logs in livewire component', function () {
    $log = MikrotikJobLog::create([
        'router_id' => $this->router->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'job_type' => MikrotikJobType::ProvisionPppoe,
        'status' => MikrotikJobStatus::Failed,
        'error_message' => 'Connection timed out',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(LogIndex::class)
        ->assertOk()
        ->assertSee('Connection timed out')
        ->assertSee($this->layanan->ppp_username);
});

test('can retry failed job from livewire log component', function () {
    Queue::fake([ProvisionPppoeAccountJob::class]);

    $log = MikrotikJobLog::create([
        'router_id' => $this->router->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'job_type' => MikrotikJobType::ProvisionPppoe,
        'status' => MikrotikJobStatus::Failed,
        'error_message' => 'Connection refused',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(LogIndex::class)
        ->call('retryJob', $log->id)
        ->assertHasNoErrors();

    Queue::assertPushed(ProvisionPppoeAccountJob::class, function ($job) {
        return $job->layanan->id === $this->layanan->id;
    });
});
