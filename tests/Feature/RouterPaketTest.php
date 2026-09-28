<?php

use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Jobs\Mikrotik\HapusPaketProfileJob;
use App\Jobs\Mikrotik\SyncIpPoolToRouterJob;
use App\Jobs\Mikrotik\SyncRouterPaketJob;
use App\Livewire\PaketLayanan\Show;
use App\Livewire\Router\Edit as RouterEdit;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Router;
use App\Models\RouterPaket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Queue::fake();

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');

    $this->paket = PaketLayanan::factory()->create(['nama_paket' => 'Karyawan_100Mbps']);
    $this->router = Router::factory()->online()->create();
    $this->pool = IpPool::factory()->create(['router_id' => $this->router->id]);
});

test('NOC menambah router ke paket dengan pool milik router itu dan profile diterapkan ke router', function () {
    $poolRouterLain = IpPool::factory()->create();

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['paketLayanan' => $this->paket])
        ->call('openCreateModal')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $poolRouterLain->id)
        ->call('simpan')
        ->assertHasErrors(['ip_pool_id' => 'exists'])
        ->set('ip_pool_id', $this->pool->id)
        ->set('deskripsi', 'Uplink ether1')
        ->call('simpan')
        ->assertHasNoErrors();

    $routerPaket = RouterPaket::sole();
    expect($routerPaket)->router_id->toBe($this->router->id)->ip_pool_id->toBe($this->pool->id)->deskripsi->toBe('Uplink ether1');
    Queue::assertPushed(SyncRouterPaketJob::class, fn ($job) => $job->routerPaket->is($routerPaket));

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['paketLayanan' => $this->paket])
        ->call('openCreateModal')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $this->pool->id)
        ->call('simpan')
        ->assertHasErrors(['router_id' => 'unique']);
});

test('router yang masih dipakai layanan paket ini tidak bisa dihapus dari paket; setelah berhenti bisa dan profile dihapus', function () {
    $routerPaket = RouterPaket::create(['paket_layanan_id' => $this->paket->id, 'router_id' => $this->router->id, 'ip_pool_id' => $this->pool->id]);
    $layanan = LayananPelanggan::factory()->create(['paket_layanan_id' => $this->paket->id, 'router_id' => $this->router->id, 'status' => StatusLayanan::Aktif]);

    $halaman = Livewire::actingAs($this->noc)->test(Show::class, ['paketLayanan' => $this->paket]);

    $halaman->call('hapus', $routerPaket->id);
    expect(RouterPaket::find($routerPaket->id))->not->toBeNull();

    $layanan->updateQuietly(['status' => StatusLayanan::Berhenti]);
    $halaman->call('hapus', $routerPaket->id);

    expect(RouterPaket::find($routerPaket->id))->toBeNull();
    Queue::assertPushed(HapusPaketProfileJob::class, fn ($job) => $job->routerId === $this->router->id && $job->namaProfile === 'Karyawan_100Mbps');
});

test('sales hanya bisa melihat detail paket, tidak bisa mengelola router', function () {
    $sales = User::factory()->create(['status' => UserStatus::Active]);
    $sales->assignRole('sales');

    Livewire::actingAs($sales)
        ->test(Show::class, ['paketLayanan' => $this->paket])
        ->assertOk()
        ->call('openCreateModal')
        ->assertForbidden();
});

test('IP Pool Isolir router dipilih di Edit Router, tidak boleh pool paket, dan pool isolir tidak bisa dipakai paket', function () {
    $poolIsolir = IpPool::factory()->create(['router_id' => $this->router->id]);
    RouterPaket::create(['paket_layanan_id' => $this->paket->id, 'router_id' => $this->router->id, 'ip_pool_id' => $this->pool->id]);
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(RouterEdit::class, ['router' => $this->router])
        ->set('ip_pool_isolir_id', $this->pool->id)
        ->call('save')
        ->assertHasErrors(['ip_pool_isolir_id' => 'unique'])
        ->set('ip_pool_isolir_id', $poolIsolir->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->router->fresh()->ip_pool_isolir_id)->toBe($poolIsolir->id);
    Queue::assertPushed(SyncIpPoolToRouterJob::class, fn ($job) => $job->ipPool->is($poolIsolir));

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['paketLayanan' => PaketLayanan::factory()->create()])
        ->call('openCreateModal')
        ->set('router_id', $this->router->id)
        ->set('ip_pool_id', $poolIsolir->id)
        ->call('simpan')
        ->assertHasErrors(['ip_pool_id' => 'unique']);
});
