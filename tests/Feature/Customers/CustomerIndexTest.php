<?php

use App\Enums\CustomerStatus;
use App\Enums\UserStatus;
use App\Livewire\Customers\Index;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->salesUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->salesUser->assignRole('sales');

    $this->teknisiUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisiUser->assignRole('teknisi');
});

test('user with view_customers permission can access customers index', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->assertOk();

    Livewire::actingAs($this->teknisiUser)
        ->test(Index::class)
        ->assertOk();
});

test('unauthenticated user is redirected to login on customers route', function () {
    $this->get(route('customers.index'))
        ->assertRedirect(route('login'));
});

test('can search customers by name, phone, or customer_code', function () {
    $c1 = Customer::factory()->create([
        'name' => 'Budi Setiawan',
        'customer_code' => 'CUST-000101',
        'phone' => '628111222333',
        'created_by' => $this->salesUser->id,
    ]);

    $c2 = Customer::factory()->create([
        'name' => 'Dewi Lestari',
        'customer_code' => 'CUST-000102',
        'phone' => '628555666777',
        'created_by' => $this->salesUser->id,
    ]);

    // Search by name
    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('search', 'Setiawan')
        ->assertSee('Budi Setiawan')
        ->assertDontSee('Dewi Lestari');

    // Search by code
    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('search', 'CUST-000102')
        ->assertSee('Dewi Lestari')
        ->assertDontSee('Budi Setiawan');

    // Search by phone
    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('search', '628111222333')
        ->assertSee('Budi Setiawan')
        ->assertDontSee('Dewi Lestari');
});

test('can filter customers by status', function () {
    Customer::factory()->create([
        'name' => 'Pelanggan Aktif',
        'status' => CustomerStatus::Active,
        'created_by' => $this->salesUser->id,
    ]);

    Customer::factory()->create([
        'name' => 'Pelanggan Nonaktif',
        'status' => CustomerStatus::Inactive,
        'created_by' => $this->salesUser->id,
    ]);

    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('filterStatus', 'inactive')
        ->assertSee('Pelanggan Nonaktif')
        ->assertDontSee('Pelanggan Aktif');
});

test('sales can filter to see only my customers', function () {
    $otherSales = User::factory()->create(['status' => UserStatus::Active]);
    $otherSales->assignRole('sales');

    $myCustomer = Customer::factory()->create([
        'name' => 'Customer Milik Saya',
        'created_by' => $this->salesUser->id,
    ]);

    $otherCustomer = Customer::factory()->create([
        'name' => 'Customer Milik Sales Lain',
        'created_by' => $otherSales->id,
    ]);

    Livewire::actingAs($this->salesUser)
        ->test(Index::class)
        ->set('filterScope', 'my')
        ->assertSee('Customer Milik Saya')
        ->assertDontSee('Customer Milik Sales Lain');
});

test('can toggle customer status and logs audit', function () {
    $customer = Customer::factory()->create([
        'status' => CustomerStatus::Active,
        'created_by' => $this->superAdmin->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('toggleStatus', $customer->id);

    expect($customer->fresh()->status)->toBe(CustomerStatus::Inactive);

    expect(AuditLog::where('action', 'customer_status_changed')->where('subject_id', $customer->id)->exists())->toBeTrue();
});

test('can delete customer with soft deletes and logs audit', function () {
    $customer = Customer::factory()->create([
        'created_by' => $this->superAdmin->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('deletingCustomerId', $customer->id)
        ->call('deleteCustomer');

    expect(Customer::find($customer->id))->toBeNull()
        ->and(Customer::withTrashed()->find($customer->id))->not->toBeNull();

    expect(AuditLog::where('action', 'customer_deleted')->where('subject_id', $customer->id)->exists())->toBeTrue();
});
