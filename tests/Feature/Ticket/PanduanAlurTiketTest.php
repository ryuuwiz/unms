<?php

use App\Enums\Ticket\DivisiTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Show;
use App\Models\LayananPelanggan;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');
});

test('panduan hanya memuat langkah divisi yang ditugaskan ke tiket', function () {
    $ticket = Ticket::factory()->gangguan()->create();
    $ticket->divisis()->create(['divisi' => DivisiTicket::Teknisi, 'status' => 'belum']);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $ticket])
        ->assertSee('Panduan Alur Gangguan')
        ->assertSee('Kerjakan di lapangan')
        ->assertDontSee('Cek dari jarak jauh');
});

test('panduan tidak tampil pada tiket pemasangan lama tanpa layanan', function () {
    $ticket = Ticket::factory()->pemasangan()->create();

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $ticket])
        ->assertDontSee('Panduan Alur');
});

test('panduan tampil pada tiket pemasangan yang mengacu layanan', function () {
    $ticket = Ticket::factory()->pemasangan()->create([
        'layanan_pelanggan_id' => LayananPelanggan::factory(),
    ]);
    $ticket->divisis()->create(['divisi' => DivisiTicket::Admin, 'status' => 'belum']);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $ticket])
        ->assertSee('Panduan Alur Pemasangan')
        ->assertSee('Tunggu invoice pertama layanan berstatus Lunas.');
});
