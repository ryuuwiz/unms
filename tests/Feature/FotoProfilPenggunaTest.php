<?php

use App\Enums\UserStatus;
use App\Livewire\Settings\Profile;
use App\Livewire\Users\Edit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');
});

test('admin mengunggah foto profil pengguna lain, thumbnail dipakai dan perubahan tercatat', function () {
    Livewire::actingAs($this->admin)
        ->test(Edit::class, ['user' => $this->teknisi])
        ->set('fotoProfil', UploadedFile::fake()->image('foto.jpg', 600, 600))
        ->assertHasNoErrors();

    $media = $this->teknisi->fresh()->getFirstMedia('foto_profil');

    expect($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($this->teknisi->fresh()->fotoProfilUrl())->toBe($media->getUrl('thumb'))
        ->and(Activity::where('subject_id', $this->teknisi->id)->where('causer_id', $this->admin->id)->count())->toBe(1);
});

test('admin menghapus foto profil pengguna lain dan penghapusan tercatat', function () {
    $this->teknisi->addMedia(UploadedFile::fake()->image('foto.jpg'))->toMediaCollection('foto_profil');

    Livewire::actingAs($this->admin)
        ->test(Edit::class, ['user' => $this->teknisi])
        ->call('hapusFotoProfil');

    expect($this->teknisi->fresh()->fotoProfilUrl())->toBeNull()
        ->and(Activity::where('subject_id', $this->teknisi->id)->where('causer_id', $this->admin->id)->count())->toBe(1);
});

test('admin mengubah foto profilnya sendiri lewat halaman edit pengguna tidak tercatat', function () {
    Livewire::actingAs($this->admin)
        ->test(Edit::class, ['user' => $this->admin])
        ->set('fotoProfil', UploadedFile::fake()->image('foto.jpg'))
        ->assertHasNoErrors();

    expect(Activity::where('causer_id', $this->admin->id)->count())->toBe(0);
});

test('pengguna tanpa izin pengguna.ubah tidak bisa mengubah foto profil orang lain', function () {
    $lain = User::factory()->create(['status' => UserStatus::Active]);

    Livewire::actingAs($this->teknisi)
        ->test(Edit::class, ['user' => $lain])
        ->set('fotoProfil', UploadedFile::fake()->image('foto.jpg'))
        ->assertForbidden();

    Livewire::actingAs($this->teknisi)
        ->test(Edit::class, ['user' => $lain])
        ->call('hapusFotoProfil')
        ->assertForbidden();

    expect($lain->fresh()->getMedia('foto_profil'))->toHaveCount(0);
});

test('pengguna menghapus foto profilnya sendiri dan kembali ke inisial', function () {
    $this->teknisi->addMedia(UploadedFile::fake()->image('foto.jpg'))->toMediaCollection('foto_profil');

    Livewire::actingAs($this->teknisi)
        ->test(Profile::class)
        ->call('hapusFotoProfil');

    expect($this->teknisi->fresh()->fotoProfilUrl())->toBeNull();
});

test('foto profil lama tanpa thumbnail memakai foto asli', function () {
    $media = $this->teknisi->addMedia(UploadedFile::fake()->image('foto.jpg'))->toMediaCollection('foto_profil');
    $media->markAsConversionNotGenerated('thumb')->save();

    expect($this->teknisi->fresh()->fotoProfilUrl())->toBe($media->getUrl());
});
