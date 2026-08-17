<?php

use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Index;
use App\Models\Pelanggan;
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
});

test('can list and paginate pelanggans', function () {
    Pelanggan::factory()->count(10)->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->assertOk()
        ->assertViewHas('pelanggans', fn ($p) => $p->count() === 10);
});

test('can search pelanggans by name or no_hp', function () {
    Pelanggan::factory()->create(['nama_depan' => 'Rian', 'nama_belakang' => 'Hidayat', 'no_hp' => '628123456789']);
    Pelanggan::factory()->create(['nama_depan' => 'Siti', 'nama_belakang' => 'Aminah', 'no_hp' => '628987654321']);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('search', 'Rian')
        ->assertSee('Rian')
        ->assertDontSee('Siti');
});

test('sales can filter to view only their own pelanggans', function () {
    Pelanggan::factory()->create(['nama_depan' => 'Milik Sales 1', 'dibuat_oleh' => $this->sales1->id]);
    Pelanggan::factory()->create(['nama_depan' => 'Milik Sales 2', 'dibuat_oleh' => $this->sales2->id]);

    Livewire::actingAs($this->sales1)
        ->test(Index::class)
        ->set('filterScope', 'my')
        ->assertSee('Milik Sales 1')
        ->assertDontSee('Milik Sales 2');
});
