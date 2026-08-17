<?php

use App\Enums\PackageStatus;
use App\Enums\UserStatus;
use App\Livewire\Packages\Index;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->salesUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->salesUser->assignRole('sales');

    $this->teknisiUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisiUser->assignRole('teknisi');

    $this->nocUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->nocUser->assignRole('noc');
});

test('user with view_packages permission can access packages index', function () {
    Livewire::actingAs($this->superAdmin)->test(Index::class)->assertOk();
    Livewire::actingAs($this->adminUser)->test(Index::class)->assertOk();
    Livewire::actingAs($this->salesUser)->test(Index::class)->assertOk();
    Livewire::actingAs($this->teknisiUser)->test(Index::class)->assertOk();
    Livewire::actingAs($this->nocUser)->test(Index::class)->assertOk();
});

test('unauthenticated user is redirected to login on packages route', function () {
    $this->get(route('packages.index'))
        ->assertRedirect(route('login'));
});

test('admin can create package via modal and it records audit log', function () {
    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->call('openCreateModal')
        ->assertSet('isModalOpen', true)
        ->set('name', 'Home Super 30 Mbps')
        ->set('download_speed_mbps', 30)
        ->set('upload_speed_mbps', 15)
        ->set('price', 275000)
        ->set('description', 'Paket internet cepat untuk kebutuhan rumah tangga.')
        ->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('isModalOpen', false);

    $package = Package::where('name', 'Home Super 30 Mbps')->first();
    expect($package)->not->toBeNull()
        ->and($package->download_speed_mbps)->toBe(30)
        ->and($package->upload_speed_mbps)->toBe(15)
        ->and($package->price)->toBe(275000)
        ->and($package->status)->toBe(PackageStatus::Active);

    expect(AuditLog::where('action', 'package_created')->where('subject_id', $package->id)->exists())->toBeTrue();
});

test('validation prevents creating package with invalid data', function () {
    Package::factory()->create(['name' => 'Home Existing 20 Mbps']);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->call('openCreateModal')
        ->set('name', 'Home Existing 20 Mbps') // duplicate
        ->set('download_speed_mbps', 0) // invalid min: 1
        ->set('upload_speed_mbps', -5) // invalid min: 1
        ->set('price', 0) // invalid min: 1
        ->call('save')
        ->assertHasErrors([
            'name' => 'unique',
            'download_speed_mbps' => 'min',
            'upload_speed_mbps' => 'min',
            'price' => 'min',
        ]);
});

test('changing download speed auto suggests upload speed if not manually altered', function () {
    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->call('openCreateModal')
        ->set('download_speed_mbps', 50)
        ->assertSet('upload_speed_mbps', 50);
});

test('admin can edit existing package via modal and it records audit log', function () {
    $package = Package::factory()->create([
        'name' => 'Home 25 Mbps',
        'download_speed_mbps' => 25,
        'upload_speed_mbps' => 25,
        'price' => 220000,
        'status' => PackageStatus::Active,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->call('openEditModal', $package->id)
        ->assertSet('isModalOpen', true)
        ->assertSet('name', 'Home 25 Mbps')
        ->set('name', 'Home 25 Mbps Pro')
        ->set('price', 240000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('isModalOpen', false);

    $fresh = $package->fresh();
    expect($fresh->name)->toBe('Home 25 Mbps Pro')
        ->and($fresh->price)->toBe(240000);

    expect(AuditLog::where('action', 'package_updated')->where('subject_id', $package->id)->exists())->toBeTrue();
});

test('admin can toggle package status and it records audit log', function () {
    $package = Package::factory()->create(['status' => PackageStatus::Active]);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->call('toggleStatus', $package->id);

    expect($package->fresh()->status)->toBe(PackageStatus::Inactive);

    expect(AuditLog::where('action', 'package_status_changed')->where('subject_id', $package->id)->exists())->toBeTrue();
});

test('admin can soft delete package and it records audit log', function () {
    $package = Package::factory()->create();

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->call('confirmDelete', $package->id)
        ->assertSet('deletingPackageId', $package->id)
        ->call('deletePackage')
        ->assertSet('deletingPackageId', null);

    expect(Package::find($package->id))->toBeNull()
        ->and(Package::withTrashed()->find($package->id))->not->toBeNull();

    expect(AuditLog::where('action', 'package_deleted')->where('subject_id', $package->id)->exists())->toBeTrue();
});

test('sales user cannot create or edit or delete packages', function () {
    $package = Package::factory()->create();

    // Sales cannot create
    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->call('openCreateModal')
        ->assertForbidden();

    // Sales cannot edit
    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->call('openEditModal', $package->id)
        ->assertForbidden();

    // Sales cannot toggle status
    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->call('toggleStatus', $package->id)
        ->assertForbidden();

    // Sales cannot delete
    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('deletingPackageId', $package->id)
        ->call('deletePackage')
        ->assertForbidden();
});

test('can search packages by name or description', function () {
    $p1 = Package::factory()->create([
        'name' => 'Gamer Extreme 100 Mbps',
        'description' => 'Paket khusus gamer dengan low latency',
    ]);

    $p2 = Package::factory()->create([
        'name' => 'Office Basic 20 Mbps',
        'description' => 'Paket browsing kantor',
    ]);

    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('search', 'Extreme')
        ->assertSee('Gamer Extreme 100 Mbps')
        ->assertDontSee('Office Basic 20 Mbps');

    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('search', 'low latency')
        ->assertSee('Gamer Extreme 100 Mbps')
        ->assertDontSee('Office Basic 20 Mbps');
});

test('can filter packages by status', function () {
    Package::factory()->create([
        'name' => 'Paket Aktif 10 Mbps',
        'status' => PackageStatus::Active,
    ]);

    Package::factory()->create([
        'name' => 'Paket Nonaktif 5 Mbps',
        'status' => PackageStatus::Inactive,
    ]);

    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('filterStatus', 'inactive')
        ->assertSee('Paket Nonaktif 5 Mbps')
        ->assertDontSee('Paket Aktif 10 Mbps');
});

test('package model helper methods return formatted strings', function () {
    $symmetric = Package::factory()->create([
        'download_speed_mbps' => 20,
        'upload_speed_mbps' => 20,
        'price' => 200000,
    ]);

    $asymmetric = Package::factory()->create([
        'download_speed_mbps' => 50,
        'upload_speed_mbps' => 25,
        'price' => 350000,
    ]);

    expect($symmetric->speedLabel())->toBe('20 Mbps (1:1)')
        ->and($symmetric->formattedPrice())->toBe('Rp 200.000 / bln')
        ->and($asymmetric->speedLabel())->toBe('50 / 25 Mbps')
        ->and($asymmetric->formattedPrice())->toBe('Rp 350.000 / bln');
});
