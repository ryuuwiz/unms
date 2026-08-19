<?php

use App\Actions\Ticket\UbahStatusTicketAction;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Exceptions\TransisiStatusTidakValidException;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Notifications\TicketStatusBerubahNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->nocUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->nocUser->assignRole('noc');

    $this->teknisiUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisiUser->assignRole('teknisi');

    $this->otherTeknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->otherTeknisi->assignRole('teknisi');

    $this->salesUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->salesUser->assignRole('sales');

    $this->pelanggan = Pelanggan::factory()->create(['dibuat_oleh' => $this->salesUser->id]);
    $this->action = app(UbahStatusTicketAction::class);
});

test('admin can execute complete valid lifecycle transisi from baru to selesai and records history', function () {
    Notification::fake();

    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'jenis' => JenisTicket::Pemasangan,
        'status' => StatusTicket::Baru,
        'dibuat_oleh' => $this->salesUser->id,
        'pic_id' => $this->teknisiUser->id,
    ]);

    // 1. Baru -> Diproses
    $this->action->execute($ticket, StatusTicket::Diproses, $this->adminUser, 'Mulai dikerjakan oleh tim.');
    expect($ticket->fresh()->status)->toBe(StatusTicket::Diproses);

    // 2. Diproses -> Menunggu Konfirmasi
    $this->action->execute($ticket, StatusTicket::MenungguKonfirmasi, $this->adminUser, 'Pemasangan kabel dan modem selesai.');
    expect($ticket->fresh()->status)->toBe(StatusTicket::MenungguKonfirmasi);

    // 3. Menunggu Konfirmasi -> Selesai
    $this->action->execute($ticket, StatusTicket::Selesai, $this->adminUser, 'Pekerjaan terverifikasi.');
    $ticketFresh = $ticket->fresh();

    expect($ticketFresh->status)->toBe(StatusTicket::Selesai)
        ->and($ticketFresh->perlu_aktivasi_manual)->toBeTrue();

    // Verify 3 history records created
    $histories = TicketHistori::where('ticket_id', $ticket->id)->get();
    expect($histories->count())->toBe(3);

    Notification::assertSentTo($this->salesUser, TicketStatusBerubahNotification::class);
    Notification::assertSentTo($this->teknisiUser, TicketStatusBerubahNotification::class);
});

test('action throws TransisiStatusTidakValidException when attempting invalid state transition', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Baru,
    ]);

    expect(fn () => $this->action->execute($ticket, StatusTicket::Selesai, $this->adminUser))
        ->toThrow(TransisiStatusTidakValidException::class);

    expect($ticket->fresh()->status)->toBe(StatusTicket::Baru);
    expect(TicketHistori::where('ticket_id', $ticket->id)->count())->toBe(0);
});

test('teknisi can only transition status on tickets assigned to themselves', function () {
    $ticketAssigned = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'jenis' => JenisTicket::Pemasangan,
        'status' => StatusTicket::Baru,
        'pic_id' => $this->teknisiUser->id,
    ]);

    $ticketOther = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'jenis' => JenisTicket::Pemasangan,
        'status' => StatusTicket::Baru,
        'pic_id' => $this->otherTeknisi->id,
    ]);

    // Assigned ticket -> success
    $this->action->execute($ticketAssigned, StatusTicket::Diproses, $this->teknisiUser, 'Saya mulai perjalanan');
    expect($ticketAssigned->fresh()->status)->toBe(StatusTicket::Diproses);

    // Other ticket -> AuthorizationException
    expect(fn () => $this->action->execute($ticketOther, StatusTicket::Diproses, $this->teknisiUser))
        ->toThrow(AuthorizationException::class);
});

test('teknisi cannot directly mark ticket as selesai without admin/noc confirmation', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'jenis' => JenisTicket::Pemasangan,
        'status' => StatusTicket::MenungguKonfirmasi,
        'pic_id' => $this->teknisiUser->id,
    ]);

    expect(fn () => $this->action->execute($ticket, StatusTicket::Selesai, $this->teknisiUser))
        ->toThrow(AuthorizationException::class);
});

test('cancelling ticket requires a reason note with minimum 5 characters', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Baru,
    ]);

    expect(fn () => $this->action->execute($ticket, StatusTicket::Batal, $this->adminUser, ''))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->action->execute($ticket, StatusTicket::Batal, $this->adminUser, 'tes'))
        ->toThrow(InvalidArgumentException::class);

    $this->action->execute($ticket, StatusTicket::Batal, $this->adminUser, 'Pelanggan membatalkan permohonan pemasangan.');
    expect($ticket->fresh()->status)->toBe(StatusTicket::Batal);
});

test('sales can only cancel tickets they created', function () {
    $ticketOwn = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Baru,
        'dibuat_oleh' => $this->salesUser->id,
    ]);

    $ticketOther = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Baru,
        'dibuat_oleh' => $this->adminUser->id,
    ]);

    // Own ticket -> can cancel
    $this->action->execute($ticketOwn, StatusTicket::Batal, $this->salesUser, 'Prospek membatalkan');
    expect($ticketOwn->fresh()->status)->toBe(StatusTicket::Batal);

    // Other ticket -> AuthorizationException
    expect(fn () => $this->action->execute($ticketOther, StatusTicket::Batal, $this->salesUser, 'Coba batalkan'))
        ->toThrow(AuthorizationException::class);
});
