<?php

use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Edit;
use App\Models\Pelanggan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->sales1 = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales1->assignRole('sales');

    $this->sales2 = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales2->assignRole('sales');

    $this->pelanggan1 = Pelanggan::factory()->create([
        'nama_depan' => 'Pelanggan',
        'nama_belakang' => 'Asli',
        'no_hp' => '628111222333',
        'dibuat_oleh' => $this->sales1->id,
    ]);
});

test('super admin can edit any pelanggan', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Edit::class, ['pelanggan' => $this->pelanggan1])
        ->set('nama_depan', 'Pelanggan Diupdate Admin')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('pelanggan.index'));

    expect($this->pelanggan1->fresh()->nama_depan)->toBe('Pelanggan Diupdate Admin');
    expect(Activity::where('subject_type', Pelanggan::class)->where('subject_id', $this->pelanggan1->id)->exists())->toBeTrue();
});

test('sales can edit pelanggan they created', function () {
    Livewire::actingAs($this->sales1)
        ->test(Edit::class, ['pelanggan' => $this->pelanggan1])
        ->set('nama_depan', 'Nama Baru Oleh Sales 1')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->pelanggan1->fresh()->nama_depan)->toBe('Nama Baru Oleh Sales 1');
});

test('sales cannot edit pelanggan created by another user', function () {
    $this->actingAs($this->sales2)
        ->get(route('pelanggan.edit', $this->pelanggan1))
        ->assertForbidden();
});
