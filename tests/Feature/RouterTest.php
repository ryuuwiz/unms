<?php

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\StatusRouter;
use App\Livewire\Router\Create;
use App\Livewire\Router\Edit;
use App\Livewire\Router\Index;
use App\Models\MikrotikJobLog;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

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
