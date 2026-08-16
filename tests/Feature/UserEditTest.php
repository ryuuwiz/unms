<?php

use App\Enums\UserStatus;
use App\Livewire\Users\Edit;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'manage_users']);
    Permission::firstOrCreate(['name' => 'manage_roles']);

    $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
    $superAdminRole->syncPermissions(['manage_users', 'manage_roles']);

    Role::firstOrCreate(['name' => 'admin']);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->targetUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->targetUser->assignRole('admin');
});

it('generates a new password and shows it', function () {
    actingAs($this->superAdmin);

    $component = Livewire::test(Edit::class, ['user' => $this->targetUser])
        ->call('resetPassword');

    $component->assertSet('generatedPassword', fn ($val) => strlen($val) === 12);
});

it('logs the password reset in audit log', function () {
    actingAs($this->superAdmin);

    Livewire::test(Edit::class, ['user' => $this->targetUser])
        ->call('resetPassword');

    expect(
        AuditLog::where('action', 'password_reset')
            ->where('subject_id', $this->targetUser->id)
            ->exists()
    )->toBeTrue();
});

it('actually changes the user password after reset', function () {
    actingAs($this->superAdmin);

    $oldPasswordHash = $this->targetUser->password;

    Livewire::test(Edit::class, ['user' => $this->targetUser])
        ->call('resetPassword');

    expect($this->targetUser->fresh()->password)->not->toBe($oldPasswordHash);
});

it('displays grouped permissions for user with a role', function () {
    actingAs($this->superAdmin);

    $component = Livewire::test(Edit::class, ['user' => $this->superAdmin]);

    $component->assertViewHas('groupedPermissions', fn ($perms) => ! empty($perms));
});
