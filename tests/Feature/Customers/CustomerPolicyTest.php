<?php

use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\User;
use App\Policies\CustomerPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->policy = new CustomerPolicy;

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

    $this->customerOfSales1 = Customer::factory()->create(['created_by' => $this->sales1->id]);
});

test('viewAny allows staff with view_customers and rejects unprivileged', function () {
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

test('update allows super_admin and admin for any customer', function () {
    expect($this->policy->update($this->superAdmin, $this->customerOfSales1))->toBeTrue()
        ->and($this->policy->update($this->admin, $this->customerOfSales1))->toBeTrue();
});

test('update allows sales only for customers they created', function () {
    expect($this->policy->update($this->sales1, $this->customerOfSales1))->toBeTrue()
        ->and($this->policy->update($this->sales2, $this->customerOfSales1))->toBeFalse();
});

test('update denies noc and teknisi', function () {
    expect($this->policy->update($this->noc, $this->customerOfSales1))->toBeFalse()
        ->and($this->policy->update($this->teknisi, $this->customerOfSales1))->toBeFalse();
});

test('delete follows the same ownership rules as update', function () {
    expect($this->policy->delete($this->superAdmin, $this->customerOfSales1))->toBeTrue()
        ->and($this->policy->delete($this->admin, $this->customerOfSales1))->toBeTrue()
        ->and($this->policy->delete($this->sales1, $this->customerOfSales1))->toBeTrue()
        ->and($this->policy->delete($this->sales2, $this->customerOfSales1))->toBeFalse()
        ->and($this->policy->delete($this->noc, $this->customerOfSales1))->toBeFalse();
});
