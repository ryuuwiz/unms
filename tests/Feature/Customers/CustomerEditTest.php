<?php

use App\Enums\CustomerStatus;
use App\Enums\UserStatus;
use App\Livewire\Customers\Edit;
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

    $this->sales1 = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales1->assignRole('sales');

    $this->sales2 = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales2->assignRole('sales');

    $this->customer1 = Customer::factory()->create([
        'customer_code' => 'CUST-000001',
        'name' => 'Pelanggan Asli',
        'phone' => '628111222333',
        'created_by' => $this->sales1->id,
    ]);
});

test('super admin can edit any customer', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Edit::class, ['customer' => $this->customer1])
        ->set('name', 'Pelanggan Diupdate Admin')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('customers.index'));

    expect($this->customer1->fresh()->name)->toBe('Pelanggan Diupdate Admin');
    expect(AuditLog::where('action', 'customer_updated')->where('subject_id', $this->customer1->id)->exists())->toBeTrue();
});

test('sales can edit customer they created', function () {
    Livewire::actingAs($this->sales1)
        ->test(Edit::class, ['customer' => $this->customer1])
        ->set('name', 'Nama Baru Oleh Sales 1')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->customer1->fresh()->name)->toBe('Nama Baru Oleh Sales 1');
});

test('sales cannot edit customer created by another user', function () {
    $this->actingAs($this->sales2)
        ->get(route('customers.edit', $this->customer1))
        ->assertForbidden();
});

test('toggling status in edit component records audit log', function () {
    Livewire::actingAs($this->sales1)
        ->test(Edit::class, ['customer' => $this->customer1])
        ->call('toggleStatus');

    expect($this->customer1->fresh()->status)->toBe(CustomerStatus::Inactive);
    expect(AuditLog::where('action', 'customer_status_changed')->where('subject_id', $this->customer1->id)->exists())->toBeTrue();
});
