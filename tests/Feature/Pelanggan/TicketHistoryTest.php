<?php

use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\Pelanggan\TicketHistory;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->pelanggan = Pelanggan::factory()->create();
});

test('it lists only tickets belonging to the given pelanggan', function () {
    $ticketMilikPelanggan = Ticket::factory()->create(['pelanggan_id' => $this->pelanggan->id]);
    $pelangganLain = Pelanggan::factory()->create();
    $ticketMilikPelangganLain = Ticket::factory()->create(['pelanggan_id' => $pelangganLain->id]);

    Livewire::actingAs($this->adminUser)
        ->test(TicketHistory::class, ['pelanggan' => $this->pelanggan])
        ->assertOk()
        ->assertSee($ticketMilikPelanggan->nomor_ticket)
        ->assertDontSee($ticketMilikPelangganLain->nomor_ticket);
});

test('status filter narrows the ticket history list', function () {
    $ticketBaru = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Baru,
    ]);
    $ticketSelesai = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Selesai,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(TicketHistory::class, ['pelanggan' => $this->pelanggan])
        ->set('status', StatusTicket::Selesai->value)
        ->assertSee($ticketSelesai->nomor_ticket)
        ->assertDontSee($ticketBaru->nomor_ticket);
});
