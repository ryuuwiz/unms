<?php

use App\Enums\StatusPelanggan;
use App\Enums\UserStatus;
use App\Models\Pelanggan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create([
        'name' => 'Super Admin User',
        'email' => 'superadmin@unms.test',
        'status' => UserStatus::Active,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->staffUser = User::factory()->create([
        'name' => 'Staff Teknisi',
        'email' => 'teknisi@unms.test',
        'status' => UserStatus::Active,
    ]);
    $this->staffUser->assignRole('teknisi');
});

test('super_admin can impersonate active staff user and redirect to dashboard', function () {
    Event::fake([TakeImpersonation::class]);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $this->staffUser->id, 'guardName' => 'web']));

    $response->assertRedirect(route('dashboard'));

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($this->staffUser->id)
        ->and(app('impersonate')->isImpersonating())->toBeTrue()
        ->and(app('impersonate')->getImpersonatorId())->toBe($this->superAdmin->id);

    Event::assertDispatched(TakeImpersonation::class);
});

test('non-super_admin staff user cannot impersonate anyone (returns 403)', function () {
    $targetUser = User::factory()->create(['status' => UserStatus::Active]);
    $targetUser->assignRole('admin');

    $response = $this->actingAs($this->staffUser)
        ->get(route('impersonate', ['id' => $targetUser->id]));

    $response->assertForbidden();
    expect(app('impersonate')->isImpersonating())->toBeFalse();
});

test('super_admin cannot impersonate another super_admin (returns 403)', function () {
    $anotherSuperAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $anotherSuperAdmin->assignRole('super_admin');

    $response = $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $anotherSuperAdmin->id]));

    $response->assertForbidden();
    expect(app('impersonate')->isImpersonating())->toBeFalse();
});

test('super_admin cannot impersonate an inactive staff user (returns 403)', function () {
    $inactiveUser = User::factory()->create(['status' => UserStatus::Inactive]);
    $inactiveUser->assignRole('teknisi');

    $response = $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $inactiveUser->id]));

    $response->assertForbidden();
    expect(app('impersonate')->isImpersonating())->toBeFalse();
});

test('super_admin cannot impersonate oneself (returns 403)', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $this->superAdmin->id]));

    $response->assertForbidden();
    expect(app('impersonate')->isImpersonating())->toBeFalse();
});

test('super_admin can impersonate active customer portal account and redirect to portal dashboard', function () {
    Event::fake([TakeImpersonation::class]);

    $pelanggan = Pelanggan::factory()->create([
        'status' => StatusPelanggan::Aktif,
        'email' => 'pelanggan@test.com',
    ]);
    $akun = $pelanggan->akunPelanggan;

    $response = $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $akun->id, 'guardName' => 'pelanggan']));

    $response->assertRedirect(route('portal.dashboard'));

    expect(Auth::guard('pelanggan')->check())->toBeTrue()
        ->and(Auth::guard('pelanggan')->id())->toBe($akun->id)
        ->and(app('impersonate')->isImpersonating())->toBeTrue()
        ->and(app('impersonate')->getImpersonatorId())->toBe($this->superAdmin->id);

    Event::assertDispatched(TakeImpersonation::class);
});

test('super_admin cannot impersonate inactive customer portal account (returns 403)', function () {
    $pelanggan = Pelanggan::factory()->create([
        'status' => StatusPelanggan::TidakAktif,
        'email' => 'inactive_customer@test.com',
    ]);
    $akun = $pelanggan->akunPelanggan;

    $response = $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $akun->id, 'guardName' => 'pelanggan']));

    $response->assertForbidden();
    expect(app('impersonate')->isImpersonating())->toBeFalse();
});

test('impersonator can leave staff impersonation and restore original super_admin session', function () {
    Event::fake([LeaveImpersonation::class]);

    $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $this->staffUser->id, 'guardName' => 'web']));

    expect(app('impersonate')->isImpersonating())->toBeTrue();

    $response = $this->get(route('impersonate.leave'));

    $response->assertRedirect(route('users.index'));

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($this->superAdmin->id)
        ->and(app('impersonate')->isImpersonating())->toBeFalse();

    Event::assertDispatched(LeaveImpersonation::class);
});

test('impersonator can leave customer portal impersonation and restore original super_admin session', function () {
    Event::fake([LeaveImpersonation::class]);

    $pelanggan = Pelanggan::factory()->create([
        'status' => StatusPelanggan::Aktif,
        'email' => 'pelanggan2@test.com',
    ]);
    $akun = $pelanggan->akunPelanggan;

    $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $akun->id, 'guardName' => 'pelanggan']));

    expect(app('impersonate')->isImpersonating())->toBeTrue();

    $response = $this->get(route('impersonate.leave'));

    $response->assertRedirect(route('pelanggan.index'));

    expect(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('web')->id())->toBe($this->superAdmin->id)
        ->and(app('impersonate')->isImpersonating())->toBeFalse();

    Event::assertDispatched(LeaveImpersonation::class);
});

test('cannot call leave impersonation when not impersonating (returns 403)', function () {
    $response = $this->actingAs($this->superAdmin)
        ->get(route('impersonate.leave'));

    $response->assertForbidden();
});

test('routes protected by impersonate.protect cannot be accessed when impersonating', function () {
    $pelanggan = Pelanggan::factory()->create([
        'status' => StatusPelanggan::Aktif,
        'email' => 'pelanggan_protect@test.com',
    ]);
    $akun = $pelanggan->akunPelanggan;

    $this->actingAs($this->superAdmin)
        ->get(route('impersonate', ['id' => $akun->id, 'guardName' => 'pelanggan']));

    // Try accessing portal ganti-password while impersonating - middleware redirects back
    $response = $this->from(route('portal.dashboard'))->get(route('portal.ganti-password'));
    $response->assertRedirect(route('portal.dashboard'));
});
