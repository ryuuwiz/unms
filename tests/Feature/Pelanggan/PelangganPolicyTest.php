<?php

use App\Enums\UserStatus;
use App\Models\Pelanggan;
use App\Models\User;
use App\Policies\PelangganPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->policy = new PelangganPolicy;

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->sales1 = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales1->assignRole('sales');

    $this->sales2 = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales2->assignRole('sales');

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->pelangganSales1 = Pelanggan::factory()->create(['dibuat_oleh' => $this->sales1->id]);
});

test('viewAny allows users with pelanggan.lihat permission', function () {
    expect($this->policy->viewAny($this->superAdmin))->toBeTrue()
        ->and($this->policy->viewAny($this->admin))->toBeTrue()
        ->and($this->policy->viewAny($this->sales1))->toBeTrue()
        ->and($this->policy->viewAny($this->noc))->toBeTrue()
        ->and($this->policy->viewAny($this->teknisi))->toBeTrue();
});

test('create allows super_admin, admin, and sales, but denies noc and teknisi', function () {
    expect($this->policy->create($this->superAdmin))->toBeTrue()
        ->and($this->policy->create($this->admin))->toBeTrue()
        ->and($this->policy->create($this->sales1))->toBeTrue()
        ->and($this->policy->create($this->noc))->toBeFalse()
        ->and($this->policy->create($this->teknisi))->toBeFalse();
});

test('update allows super_admin and admin for any pelanggan', function () {
    expect($this->policy->update($this->superAdmin, $this->pelangganSales1))->toBeTrue()
        ->and($this->policy->update($this->admin, $this->pelangganSales1))->toBeTrue();
});

test('update allows sales only for pelanggans they created', function () {
    expect($this->policy->update($this->sales1, $this->pelangganSales1))->toBeTrue()
        ->and($this->policy->update($this->sales2, $this->pelangganSales1))->toBeFalse();
});

test('update denies noc and teknisi', function () {
    expect($this->policy->update($this->noc, $this->pelangganSales1))->toBeFalse()
        ->and($this->policy->update($this->teknisi, $this->pelangganSales1))->toBeFalse();
});
