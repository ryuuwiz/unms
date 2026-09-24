<?php

use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Riwayat;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->tiketTeknisi = Ticket::factory()->create(['pic_id' => $this->teknisi->id]);
    $this->tiketLain = Ticket::factory()->create();

    // created_at tidak fillable (diisi default DB), jadi dipasang lewat forceFill.
    (new TicketHistori)->forceFill([
        'ticket_id' => $this->tiketTeknisi->id, 'status_lama' => StatusTicket::Baru, 'status_baru' => StatusTicket::Diproses,
        'catatan' => 'Teknisi berangkat ke lokasi', 'oleh_pengguna_id' => $this->teknisi->id, 'created_at' => '2026-09-20 09:00:00',
    ])->save();
    (new TicketHistori)->forceFill([
        'ticket_id' => $this->tiketLain->id, 'status_lama' => StatusTicket::Diproses, 'status_baru' => StatusTicket::Selesai,
        'catatan' => 'Gangguan kabel selesai diperbaiki', 'oleh_pengguna_id' => $this->admin->id, 'created_at' => '2026-09-22 15:00:00',
    ])->save();
});

test('riwayat menampilkan histori lintas tiket dan dapat difilter per status serta tanggal', function () {
    Livewire::actingAs($this->admin)
        ->test(Riwayat::class)
        ->assertSee('Teknisi berangkat ke lokasi')
        ->assertSee('Gangguan kabel selesai diperbaiki')
        ->set('status', StatusTicket::Selesai->value)
        ->assertSee('Gangguan kabel selesai diperbaiki')
        ->assertDontSee('Teknisi berangkat ke lokasi')
        ->set('status', '')
        ->set('dari', '2026-09-21')
        ->assertSee('Gangguan kabel selesai diperbaiki')
        ->assertDontSee('Teknisi berangkat ke lokasi');
});

test('teknisi hanya melihat riwayat tiket yang PIC-nya dirinya', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Riwayat::class)
        ->assertSee('Teknisi berangkat ke lokasi')
        ->assertDontSee('Gangguan kabel selesai diperbaiki');
});

test('halaman riwayat tiket dapat dibuka lewat route', function () {
    $this->actingAs($this->admin)->get(route('ticket.riwayat'))->assertOk()->assertSee($this->tiketLain->nomor_ticket);
});
