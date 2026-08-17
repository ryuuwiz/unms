<?php

use App\Enums\UserStatus;
use App\Livewire\Roles\Create;
use App\Livewire\Roles\Edit;
use App\Livewire\Roles\Index;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->regularUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->regularUser->assignRole('admin');
});

it('allows super_admin to view roles index', function () {
    actingAs($this->superAdmin)
        ->get(route('roles.index'))
        ->assertOk();
});

it('blocks regular user without peran.lihat from accessing roles index', function () {
    actingAs($this->regularUser)
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('can create a new role', function () {
    actingAs($this->superAdmin);

    Livewire::test(Create::class)
        ->set('name', 'supervisor')
        ->set('selectedPermissions', ['pelanggan.lihat'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::where('name', 'supervisor')->first();

    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('pelanggan.lihat'))->toBeTrue();
});

it('blocks duplicate role name on create', function () {
    actingAs($this->superAdmin);

    Livewire::test(Create::class)
        ->set('name', 'super_admin')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

it('can update role permissions', function () {
    actingAs($this->superAdmin);

    $role = Role::firstOrCreate(['name' => 'test_role']);
    $role->syncPermissions([]);

    Livewire::test(Edit::class, ['role' => $role])
        ->set('selectedPermissions', ['pelanggan.lihat'])
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->hasPermissionTo('pelanggan.lihat'))->toBeTrue();
});

it('deletes a role that has no users', function () {
    actingAs($this->superAdmin);

    $role = Role::firstOrCreate(['name' => 'to_be_deleted']);

    Livewire::test(Index::class)
        ->call('deleteRole', $role->id)
        ->assertHasNoErrors();

    expect(Role::where('name', 'to_be_deleted')->exists())->toBeFalse();
});

it('renders permission labels properly without raw json string', function () {
    actingAs($this->superAdmin);

    $role = Role::where('name', 'admin')->first();

    Livewire::test(Edit::class, ['role' => $role])
        ->assertOk()
        ->assertSee('Pelanggan')
        ->assertSee('Lihat')
        ->assertSee('Buat')
        ->assertSee('Ubah')
        ->assertSee('Hapus')
        ->assertDontSee('"guard_name"')
        ->assertDontSee('"Guard Name"');

    Livewire::test(Create::class)
        ->assertOk()
        ->assertSee('Pelanggan')
        ->assertSee('Lihat')
        ->assertDontSee('"guard_name"')
        ->assertDontSee('"Guard Name"');
});
