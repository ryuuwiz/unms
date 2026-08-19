<?php

use App\Actions\Ticket\AssignPicAction;
use App\Enums\Ticket\JenisTicket;
use App\Enums\UserStatus;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use App\Notifications\TicketDiassignNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->teknisiUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisiUser->assignRole('teknisi');

    $this->salesUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->salesUser->assignRole('sales');

    $this->pelanggan = Pelanggan::factory()->create();
    $this->action = app(AssignPicAction::class);
});

test('admin can assign pic to ticket and logs history with notification dispatched', function () {
    Notification::fake();

    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'jenis' => JenisTicket::Pemasangan,
        'pic_id' => null,
    ]);

    $this->action->execute(
        ticket: $ticket,
        pic: $this->teknisiUser,
        actor: $this->adminUser,
        catatan: 'Prioritas tinggi, tolong ditangani hari ini.'
    );

    expect($ticket->fresh()->pic_id)->toBe($this->teknisiUser->id);

    $histori = TicketHistori::where('ticket_id', $ticket->id)->latest('id')->first();
    expect($histori)->not->toBeNull()
        ->and($histori->catatan)->toContain($this->teknisiUser->name)
        ->and($histori->catatan)->toContain('Prioritas tinggi');

    Notification::assertSentTo($this->teknisiUser, TicketDiassignNotification::class);
});

test('user without ticket.assign permission cannot assign pic', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
    ]);

    expect(fn () => $this->action->execute($ticket, $this->teknisiUser, $this->salesUser))
        ->toThrow(AuthorizationException::class);
});

test('admin can unassign pic', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'pic_id' => $this->teknisiUser->id,
    ]);

    $this->action->execute(
        ticket: $ticket,
        pic: null,
        actor: $this->adminUser,
        catatan: 'Jadwal diatur ulang.'
    );

    expect($ticket->fresh()->pic_id)->toBeNull();

    $histori = TicketHistori::where('ticket_id', $ticket->id)->latest('id')->first();
    expect($histori->catatan)->toContain('Penugasan PIC dibatalkan');
});
