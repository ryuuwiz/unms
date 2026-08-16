<?php

use App\Enums\UserStatus;
use App\Livewire\Roles\Create;
use App\Livewire\Roles\Edit;
use App\Livewire\Roles\Index;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed permissions and roles for all tests
    $this->manageRoles = Permission::firstOrCreate(['name' => 'manage_roles']);
    $this->manageUsers = Permission::firstOrCreate(['name' => 'manage_users']);

    $this->superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
    $this->superAdminRole->syncPermissions([$this->manageRoles, $this->manageUsers]);

    $this->adminRole = Role::firstOrCreate(['name' => 'admin']);

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

it('blocks non-super_admin from accessing roles index', function () {
    actingAs($this->regularUser)
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('can create a new role and logs it', function () {
    actingAs($this->superAdmin);

    $viewCustomers = Permission::firstOrCreate(['name' => 'view_customers']);

    Livewire::test(Create::class)
        ->set('name', 'supervisor')
        ->set('selectedPermissions', ['view_customers'])
        ->call('save');

    $role = Role::where('name', 'supervisor')->first();

    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('view_customers'))->toBeTrue();

    expect(AuditLog::where('action', 'role_created')->where('subject_id', $role->id)->exists())->toBeTrue();
});

it('blocks duplicate role name on create', function () {
    actingAs($this->superAdmin);

    Livewire::test(Create::class)
        ->set('name', 'super_admin') // already exists
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

it('can update a role permissions and logs the change', function () {
    actingAs($this->superAdmin);

    $viewCustomers = Permission::firstOrCreate(['name' => 'view_customers']);
    $role = Role::firstOrCreate(['name' => 'test_role']);
    $role->syncPermissions([]);

    Livewire::test(Edit::class, ['role' => $role])
        ->set('selectedPermissions', ['view_customers'])
        ->call('save');

    expect($role->fresh()->hasPermissionTo('view_customers'))->toBeTrue();

    expect(AuditLog::where('action', 'role_permissions_updated')->where('subject_id', $role->id)->exists())->toBeTrue();
});

it('deletes a role that has no users and logs it', function () {
    actingAs($this->superAdmin);

    $role = Role::firstOrCreate(['name' => 'to_be_deleted']);

    Livewire::test(Index::class)
        ->call('deleteRole', $role->id);

    expect(Role::where('name', 'to_be_deleted')->exists())->toBeFalse();
    expect(AuditLog::where('action', 'role_deleted')->exists())->toBeTrue();
});

it('blocks deleting a role that still has users', function () {
    actingAs($this->superAdmin);

    Livewire::test(Index::class)
        ->call('deleteRole', $this->adminRole->id);

    // regularUser still has admin role, so it should NOT be deleted
    expect(Role::where('name', 'admin')->exists())->toBeTrue();
});
