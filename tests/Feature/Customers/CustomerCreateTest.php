<?php

use App\Enums\CustomerStatus;
use App\Enums\UserStatus;
use App\Livewire\Customers\Create;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->salesUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->salesUser->assignRole('sales');

    $this->teknisiUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisiUser->assignRole('teknisi');
});

test('sales user can access create customer page', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->assertOk();
});

test('teknisi without manage_customers cannot access create customer page', function () {
    $this->actingAs($this->teknisiUser)
        ->get(route('customers.create'))
        ->assertForbidden();
});

test('can create a customer with valid data, normalized phone, and sequential code', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('name', 'Ahmad Dahlan')
        ->set('email', 'ahmad@example.com')
        ->set('phone', '081234567890')
        ->set('address', 'Jl. Merdeka No. 10, Jakarta')
        ->set('installation_address', 'Jl. Sudirman Kav. 5, Jakarta')
        ->set('lat', -6.2088)
        ->set('lng', 106.8456)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('customers.index'));

    $customer = Customer::where('name', 'Ahmad Dahlan')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->customer_code)->toBe('CUST-000001')
        ->and($customer->phone)->toBe('6281234567890')
        ->and($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->created_by)->toBe($this->salesUser->id)
        ->and($customer->lat)->toBe(-6.2088)
        ->and($customer->lng)->toBe(106.8456);

    expect(AuditLog::where('action', 'customer_created')->where('subject_id', $customer->id)->exists())->toBeTrue();
});

test('generates sequential customer codes for subsequent creations', function () {
    Customer::factory()->create(['customer_code' => 'CUST-000001']);

    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('name', 'Pelanggan Kedua')
        ->set('phone', '089876543210')
        ->set('installation_address', 'Jl. Thamrin No. 2')
        ->call('save')
        ->assertHasNoErrors();

    $second = Customer::where('name', 'Pelanggan Kedua')->first();
    expect($second->customer_code)->toBe('CUST-000002');
});

test('rejects invalid phone numbers', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('name', 'Budi')
        ->set('phone', '12345') // Invalid non-Indonesian prefix
        ->set('installation_address', 'Jl. Test')
        ->call('save')
        ->assertHasErrors(['phone' => 'regex']);

    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('name', 'Budi')
        ->set('phone', 'abc-1234')
        ->set('installation_address', 'Jl. Test')
        ->call('save')
        ->assertHasErrors(['phone' => 'regex']);
});

test('requires mandatory fields', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('name', '')
        ->set('phone', '')
        ->set('installation_address', '')
        ->call('save')
        ->assertHasErrors([
            'name' => 'required',
            'phone' => 'required',
            'installation_address' => 'required',
        ]);
});
