<?php

use App\Enums\RouterStatus;
use App\Livewire\Routers\Create;
use App\Livewire\Routers\Index;
use App\Models\Router;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin']);
    Role::firstOrCreate(['name' => 'admin']);
});

test('super admin can see credentials fields on create form', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get(route('routers.create'))
        ->assertOk()
        ->assertSee('Username')
        ->assertSee('Password Router');
});

test('admin cannot see credentials fields on create form', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    // Due to permission:manage_routers middleware on routes
    $this->actingAs($user)
        ->get(route('routers.create'))
        ->assertForbidden();
});

test('can create router with encrypted password', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('name', 'CORE_ROUTER_01')
        ->set('ip_address', '103.10.20.30')
        ->set('username', 'api_user')
        ->set('password', 'secret123')
        ->set('description', 'Core router at Data Center')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('routers.index'));

    $router = Router::where('name', 'CORE_ROUTER_01')->first();

    expect($router)->not->toBeNull()
        ->and($router->ip_address)->toBe('103.10.20.30')
        ->and($router->username)->toBe('api_user')
        ->and($router->status)->toBe(RouterStatus::Unknown)
        ->and($router->password)->toBe('secret123'); // Eloquent casts automatically decrypt on access

    // Verify it is encrypted in DB
    $rawValue = DB::table('routers')->where('name', 'CORE_ROUTER_01')->value('password');
    expect($rawValue)->not->toBe('secret123')
        ->and(Crypt::decryptString($rawValue))->toBe('secret123');
});

test('validates regex for name', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    Livewire::actingAs($user)
        ->test(Create::class)
        ->set('name', 'invalid-name!') // only A-Z, 0-9, _ allowed
        ->call('save')
        ->assertHasErrors(['name' => 'regex']);
});

test('can toggle status from index', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $router = Router::create([
        'name' => 'R1',
        'ip_address' => '127.0.0.1',
        'username' => 'test',
        'status' => RouterStatus::Unknown,
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('toggleStatus', $router->id)
        ->assertHasNoErrors();

    expect($router->fresh()->status)->toBe(RouterStatus::Online);
});
