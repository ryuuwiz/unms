<?php

use App\Enums\UserStatus;
use App\Livewire\Customers\Show;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->customer = Customer::factory()->create([
        'customer_code' => 'CUST-000088',
        'name' => 'Pak Harun',
        'phone' => '628123456789',
        'address' => 'Jl. Kebon Jeruk No. 5',
        'installation_address' => 'Jl. Kebon Jeruk No. 5 (Ruko Lt. 2)',
        'lat' => -6.2088,
        'lng' => 106.8456,
        'created_by' => $this->teknisi->id,
    ]);
});

test('teknisi can view customer detail overview', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['customer' => $this->customer])
        ->assertOk()
        ->assertSee('Pak Harun')
        ->assertSee('CUST-000088')
        ->assertSee('628123456789')
        ->assertSee('Jl. Kebon Jeruk No. 5 (Ruko Lt. 2)');
});

test('can switch between detail tabs', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['customer' => $this->customer])
        ->set('activeTab', 'subscriptions')
        ->assertSee('Modul Langganan & Layanan', false)
        ->set('activeTab', 'tickets')
        ->assertSee('Modul Tiket Gangguan & Laporan', false);
});

test('shows audit history tab when logs exist', function () {
    AuditLog::create([
        'user_id' => $this->teknisi->id,
        'action' => 'customer_created',
        'subject_type' => Customer::class,
        'subject_id' => $this->customer->id,
        'old_values' => null,
        'new_values' => ['name' => 'Pak Harun'],
        'ip_address' => '127.0.0.1',
    ]);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['customer' => $this->customer])
        ->set('activeTab', 'audit')
        ->assertSee('Customer Created')
        ->assertSee('127.0.0.1');
});
