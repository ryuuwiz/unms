<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusRouter;
use App\Jobs\Mikrotik\CleanupPppSecretOnOldRouterJob;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Livewire\Router\Create;
use App\Livewire\Router\Edit;
use App\Livewire\Router\Index;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->adminUser = User::factory()->create();
    $this->adminUser->assignRole('admin');

    $this->nocUser = User::factory()->create();
    $this->nocUser->assignRole('noc');
});

test('super admin can access router create page', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('router.create'))
        ->assertOk()
        ->assertSee('Username API')
        ->assertSee('Password API');
});

test('admin can access router create page', function () {
    $this->actingAs($this->adminUser)
        ->get(route('router.create'))
        ->assertOk()
        ->assertSee('Username API')
        ->assertSee('Password API');
});

test('admin can see create router button on index page', function () {
    $this->actingAs($this->adminUser)
        ->get(route('router.index'))
        ->assertOk()
        ->assertSee('Tambah Router');
});

test('can create router with hostname or domain name', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Create::class)
        ->set('nama_router', 'ROUTER_DDNS')
        ->set('ip_address', 'router1.sn.mynetname.net')
        ->set('port', 8728)
        ->set('username', 'api_user')
        ->set('password', 'secret123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('router.index'));

    $router = Router::where('nama_router', 'ROUTER_DDNS')->first();
    expect($router)->not->toBeNull()
        ->and($router->ip_address)->toBe('router1.sn.mynetname.net');
});

test('can create router with public ip and whitespace', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Create::class)
        ->set('nama_router', 'ROUTER_PUBLIC_IP')
        ->set('ip_address', ' 103.175.156.72 ')
        ->set('port', 8728)
        ->set('username', 'admin')
        ->set('password', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('router.index'));

    $router = Router::where('nama_router', 'ROUTER_PUBLIC_IP')->first();
    expect($router)->not->toBeNull()
        ->and($router->ip_address)->toBe('103.175.156.72');
});

test('can create router with exact user payload and complex password and empty port', function () {
    $complexPassword = '3Vn5Fd3>:,=cc>X<|5{|0)%qgF7d%#8372KuewGIZ?*QO;#?*B';

    Livewire::actingAs($this->superAdmin)
        ->test(Create::class)
        ->set('nama_router', 'TEST_ROUTER_EXACT')
        ->set('ip_address', '103.175.156.72')
        ->set('port', null)
        ->set('username', 'go_billing')
        ->set('password', $complexPassword)
        ->set('deskripsi', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('router.index'));

    $router = Router::where('nama_router', 'TEST_ROUTER_EXACT')->first();
    expect($router)->not->toBeNull()
        ->and($router->ip_address)->toBe('103.175.156.72')
        ->and($router->port)->toBe(8728)
        ->and($router->username)->toBe('go_billing')
        ->and($router->password_terenkripsi)->toBe($complexPassword);
});

test('can create router with encrypted password', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Create::class)
        ->set('nama_router', 'CORE_ROUTER_01')
        ->set('ip_address', '103.10.20.30')
        ->set('port', 8728)
        ->set('username', 'api_user')
        ->set('password', 'secret123')
        ->set('deskripsi', 'Core router at Data Center')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('router.index'));

    $router = Router::where('nama_router', 'CORE_ROUTER_01')->first();

    expect($router)->not->toBeNull()
        ->and($router->ip_address)->toBe('103.10.20.30')
        ->and($router->username)->toBe('api_user')
        ->and($router->status_koneksi)->toBe(StatusRouter::Unknown)
        ->and($router->password_terenkripsi)->toBe('secret123');

    $rawValue = DB::table('router')->where('nama_router', 'CORE_ROUTER_01')->value('password_terenkripsi');
    expect($rawValue)->not->toBe('secret123')
        ->and(Crypt::decryptString($rawValue))->toBe('secret123');
});

test('can toggle router status from index', function () {
    $router = Router::factory()->create([
        'nama_router' => 'R1',
        'ip_address' => '127.0.0.1',
        'status_koneksi' => StatusRouter::Online,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('toggleStatus', $router->id)
        ->assertHasNoErrors();

    expect($router->fresh()->status_koneksi)->toBe(StatusRouter::Offline);
});

test('autoRecoverPpp on router edit component calls autoRecoverPppSecrets and logs to MikrotikJobLog', function () {
    $router = Router::factory()->create([
        'nama_router' => 'R1',
        'ip_address' => '127.0.0.1',
        'status_koneksi' => StatusRouter::Online,
    ]);

    $this->mock(MikrotikService::class, function ($mock) use ($router) {
        $mock->shouldReceive('autoRecoverPppSecrets')
            ->once()
            ->with(Mockery::on(fn ($r) => $r->id === $router->id))
            ->andReturn([
                'total_checked' => 5,
                'recovered' => 2,
                'already_synced' => 3,
                'disabled' => 0,
                'duplicates_removed' => 0,
                'errors' => [],
            ]);
    });

    Livewire::actingAs($this->superAdmin)
        ->test(Edit::class, ['router' => $router])
        ->call('autoRecoverPpp')
        ->assertHasNoErrors();

    $log = MikrotikJobLog::where('router_id', $router->id)
        ->where('job_type', MikrotikJobType::ReconcilePppoe)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(MikrotikJobStatus::Success);
});

test('can delete router without relations from index', function () {
    $router = Router::factory()->create([
        'nama_router' => 'ROUTER_TO_DELETE',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $router->id)
        ->assertSet('deletingId', $router->id)
        ->call('deleteRouter')
        ->assertSet('deletingId', null)
        ->assertHasNoErrors();

    expect(Router::find($router->id))->toBeNull();
});

test('can delete router with unused ip pools and job logs from index', function () {
    $router = Router::factory()->create([
        'nama_router' => 'ROUTER_WITH_UNUSED_POOL',
    ]);

    $pool = IpPool::factory()->create([
        'router_id' => $router->id,
    ]);

    $jobLog = MikrotikJobLog::create([
        'router_id' => $router->id,
        'job_type' => MikrotikJobType::ReconcilePppoe,
        'status' => MikrotikJobStatus::Success,
        'attempt_count' => 1,
    ]);

    expect($router->canBeDeleted())->toBeTrue();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $router->id)
        ->call('deleteRouter')
        ->assertSet('deletingId', null)
        ->assertHasNoErrors();

    expect(Router::find($router->id))->toBeNull()
        ->and(IpPool::find($pool->id))->toBeNull()
        ->and(MikrotikJobLog::find($jobLog->id))->toBeNull();
});

test('can delete router and move its layanans to another router', function () {
    Queue::fake([CleanupPppSecretOnOldRouterJob::class, ProvisionPppoeAccountJob::class]);
    $routerA = Router::factory()->create(['nama_router' => 'ROUTER_A']);
    $routerB = Router::factory()->create(['nama_router' => 'ROUTER_B']);

    $pelanggan = Pelanggan::factory()->create();
    $paket = PaketLayanan::factory()->create();

    $poolB = IpPool::factory()->create(['router_id' => $routerB->id]);

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $routerA->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $routerA->id)
        ->set('targetRouterId', $routerB->id)
        ->set('targetPoolId', $poolB->id)
        ->call('deleteRouter')
        ->assertSet('deletingId', null)
        ->assertHasNoErrors();

    expect(Router::find($routerA->id))->toBeNull()
        ->and($layanan->fresh()->router_id)->toBe($routerB->id)
        ->and($layanan->fresh()->ip_pool_id)->toBe($poolB->id);

    // Secret di router lama dibersihkan lewat antrean.
    Queue::assertPushed(CleanupPppSecretOnOldRouterJob::class, fn (CleanupPppSecretOnOldRouterJob $job) => $job->oldRouterId === $routerA->id);
});

test('can delete router and force delete its layanans with confirmation', function () {
    $routerA = Router::factory()->create(['nama_router' => 'ROUTER_A_FORCE']);
    $routerB = Router::factory()->create(['nama_router' => 'ROUTER_B_OTHER']);

    $pelanggan = Pelanggan::factory()->create();
    $paket = PaketLayanan::factory()->create();

    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'router_id' => $routerA->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $routerA->id)
        ->set('confirmForceDelete', true)
        ->call('deleteRouter')
        ->assertSet('deletingId', null)
        ->assertHasNoErrors();

    expect(Router::find($routerA->id))->toBeNull()
        ->and(LayananPelanggan::find($layanan->id))->toBeNull();
});

test('user without delete permission cannot delete router', function () {
    $router = Router::factory()->create();
    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->assignRole('teknisi');

    Livewire::actingAs($unauthorizedUser)
        ->test(Index::class)
        ->call('confirmDelete', $router->id)
        ->call('deleteRouter')
        ->assertForbidden();

    expect(Router::find($router->id))->not->toBeNull();
});

test('handles invalid encrypted password gracefully without throwing DecryptException', function () {
    $router = Router::factory()->create();

    DB::table('router')->where('id', $router->id)->update([
        'password_terenkripsi' => 'invalid_encrypted_data',
    ]);

    $router->refresh();

    expect($router->password_terenkripsi)->toBeNull();
    expect($router->toArray()['password_terenkripsi'])->toBeNull();
});
