<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can upload and replace their own foto profil', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(Profile::class)
        ->set('fotoProfil', UploadedFile::fake()->image('foto1.jpg'))
        ->call('uploadFotoProfil')
        ->assertHasNoErrors();

    expect($user->fresh()->fotoProfilUrl())->not->toBeNull();
    $firstUrl = $user->fresh()->fotoProfilUrl();

    // Koleksi singleFile: unggah ulang mengganti foto lama, bukan menambah.
    $component
        ->set('fotoProfil', UploadedFile::fake()->image('foto2.jpg'))
        ->call('uploadFotoProfil')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->getMedia('foto_profil'))->toHaveCount(1)
        ->and($user->fotoProfilUrl())->not->toBe($firstUrl);
});

test('foto profil harus berupa gambar dan maksimal 2MB', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('fotoProfil', UploadedFile::fake()->create('dokumen.pdf', 100))
        ->call('uploadFotoProfil')
        ->assertHasErrors(['fotoProfil' => 'image']);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
