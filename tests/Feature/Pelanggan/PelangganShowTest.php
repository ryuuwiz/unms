<?php

use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Show;
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

    $this->pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Budi',
        'nama_belakang' => 'Santoso',
        'no_hp' => '628123456789',
        'alamat_lengkap' => 'Jl. Merdeka No. 1, Jakarta',
    ]);
});

test('can render pelanggan detail page with info and tabs', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('628123456789')
        ->assertSee('Jl. Merdeka No. 1, Jakarta');
});
