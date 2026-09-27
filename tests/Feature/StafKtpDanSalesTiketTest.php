<?php

use App\Enums\Ticket\JenisTicket;
use App\Enums\UserStatus;
use App\Livewire\Settings\Profile;
use App\Livewire\Ticket\Show;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->sales = User::factory()->create(['name' => 'Sales Rian', 'status' => UserStatus::Active]);
    $this->sales->assignRole('sales');
});

function tiketDariPendaftar(User $pendaftar, JenisTicket $jenis): Ticket
{
    $pelanggan = Pelanggan::factory()->create(['dibuat_oleh' => $pendaftar->id]);

    return Ticket::factory()->create(['pelanggan_id' => $pelanggan->id, 'jenis' => $jenis]);
}

test('Sales Penanggung Jawab hanya pada Pemasangan & Pindah Alamat, dan hanya bila pendaftar ber-peran sales', function () {
    expect(tiketDariPendaftar($this->sales, JenisTicket::Pemasangan)->salesPenanggungJawab()->is($this->sales))->toBeTrue()
        ->and(tiketDariPendaftar($this->sales, JenisTicket::PindahAlamat)->salesPenanggungJawab()->is($this->sales))->toBeTrue()
        ->and(tiketDariPendaftar($this->sales, JenisTicket::Gangguan)->salesPenanggungJawab())->toBeFalse()
        ->and(tiketDariPendaftar($this->sales, JenisTicket::Pencabutan)->salesPenanggungJawab())->toBeFalse()
        ->and(tiketDariPendaftar($this->admin, JenisTicket::Pemasangan)->salesPenanggungJawab())->toBeNull();
});

test('halaman tiket menampilkan sales nonaktif dengan badge, dan "Tanpa Sales" bila pendaftar bukan sales', function () {
    $this->sales->update(['status' => UserStatus::Inactive]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => tiketDariPendaftar($this->sales, JenisTicket::Pemasangan)])
        ->assertSee('Sales Penanggung Jawab')
        ->assertSee('Sales Rian')
        ->assertSee('Nonaktif');

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => tiketDariPendaftar($this->admin, JenisTicket::Pemasangan)])
        ->assertSee('Tanpa Sales');

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => tiketDariPendaftar($this->sales, JenisTicket::Gangguan)])
        ->assertDontSee('Sales Penanggung Jawab');
});

test('staf mengunggah KTP sendiri dan tersimpan terenkripsi di disk privat', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Profile::class)
        ->set('fotoKtp', UploadedFile::fake()->image('ktp.jpg', 600, 400))
        ->call('uploadKtp')
        ->assertHasNoErrors();

    $media = $this->teknisi->fresh()->getKtpMedia();

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('local')
        ->and(fn () => Crypt::decryptString(Storage::disk('local')->get($media->getPathRelativeToRoot())))->not->toThrow(Exception::class);
});

test('KTP staf hanya bisa dilihat pemilik dan admin, dan setiap akses tercatat', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Profile::class)
        ->set('fotoKtp', UploadedFile::fake()->image('ktp.jpg', 600, 400))
        ->call('uploadKtp');

    $url = route('users.ktp.preview', $this->teknisi);

    $this->actingAs($this->teknisi)->get($url)->assertOk();
    $this->actingAs($this->admin)->get($url)->assertOk();
    $this->actingAs($this->sales)->get($url)->assertForbidden();

    expect(Activity::where('log_name', 'pengguna')->where('subject_id', $this->teknisi->id)->count())->toBe(2);
});
