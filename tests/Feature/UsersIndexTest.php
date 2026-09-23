<?php

use App\Enums\UserStatus;
use App\Livewire\Users\Index;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super_admin can view users list', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertOk();
});

test('user without manage_users permission gets 403 on users route', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole('teknisi');

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('super_admin can search users by name', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    User::factory()->create(['name' => 'Budi Santoso', 'status' => UserStatus::Active]);
    User::factory()->create(['name' => 'Siti Aminah', 'status' => UserStatus::Active]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'Budi')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Siti Aminah');
});

test('super_admin can filter users by status', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    User::factory()->create(['name' => 'Active User', 'status' => UserStatus::Active]);
    User::factory()->create(['name' => 'Inactive User', 'status' => UserStatus::Inactive]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterStatus', 'inactive')
        ->assertSee('Inactive User')
        ->assertDontSee('Active User');
});

test('users list shows the uploaded foto profil as the avatar', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    $userWithPhoto = User::factory()->create(['name' => 'Punya Foto', 'status' => UserStatus::Active]);
    $path = storage_path('app/dummy-'.uniqid().'.png');
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    $userWithPhoto->addMedia($path)->preservingOriginal()->toMediaCollection('foto_profil');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee($userWithPhoto->fotoProfilUrl());
});
