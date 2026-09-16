<?php

use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Create;
use App\Livewire\Ticket\Index;
use App\Livewire\Ticket\Show;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
});

test('user with ticket.lihat permission can access ticket index and show page', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->assertOk()
        ->assertSee($ticket->nomor_ticket);

    Livewire::actingAs($this->adminUser)
        ->test(Show::class, ['ticket' => $ticket])
        ->assertOk()
        ->assertSee($ticket->nomor_ticket);
});

test('guest or unauthorized user cannot access ticket index', function () {
    $unauth = User::factory()->create(['status' => UserStatus::Active]); // No role/permission

    Livewire::actingAs($unauth)
        ->test(Index::class)
        ->assertForbidden();
});

test('teknisi only sees tickets assigned to them in index listing', function () {
    $ticketMine = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'pic_id' => $this->teknisiUser->id,
    ]);

    $ticketOther = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'pic_id' => $this->adminUser->id,
    ]);

    Livewire::actingAs($this->teknisiUser)
        ->test(Index::class)
        ->assertSee($ticketMine->nomor_ticket)
        ->assertDontSee($ticketOther->nomor_ticket);
});

test('admin can create ticket through livewire create form', function () {
    Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('jenis', JenisTicket::Pemasangan->value)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('prioritas', PrioritasTicket::Tinggi->value)
        ->set('divisis', [DivisiTicket::Teknisi->value])
        ->set('pic_id', $this->teknisiUser->id)
        ->set('deskripsi', 'Pemasangan paket 50Mbps di perumahan arsyila.')
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::where('pelanggan_id', $this->pelanggan->id)->first();
    expect($ticket)->not->toBeNull()
        ->and($ticket->nomor_ticket)->toMatch('/^TCK-\d{4}-\d{6}$/')
        ->and($ticket->status)->toBe(StatusTicket::Baru)
        ->and($ticket->pic_id)->toBe($this->teknisiUser->id);

    expect(TicketHistori::where('ticket_id', $ticket->id)->count())->toBe(1);
});

test('livewire show component allows changing status via modal', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'status' => StatusTicket::Baru,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('statusBaru', StatusTicket::Diproses->value)
        ->set('catatanStatus', 'Teknisi sudah mulai bergerak.')
        ->call('prosesUbahStatus')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(StatusTicket::Diproses);
});

test('livewire show component allows assigning pic via modal', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'pic_id' => null,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('selectedPicId', $this->teknisiUser->id)
        ->set('catatanAssign', 'Tolong prioritaskan.')
        ->call('prosesAssignPic')
        ->assertHasNoErrors();

    expect($ticket->fresh()->pic_id)->toBe($this->teknisiUser->id);
});

test('pencarian pelanggan mengembalikan hasil dan bisa dipilih', function () {
    $match = Pelanggan::factory()->create(['nama_depan' => 'Budi']);
    Pelanggan::factory()->create(['nama_depan' => 'Siti']);

    $component = Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('searchTerms.pelanggan_id', 'Budi');

    expect($component->instance()->searchResults('pelanggan_id'))
        ->toHaveCount(1)
        ->and($component->instance()->searchResults('pelanggan_id')[0]['id'])->toBe($match->id);

    $component->call('selectSearchable', 'pelanggan_id', $match->id)
        ->assertSet('pelanggan_id', $match->id);
});

test('hasil pencarian pelanggan dibatasi 20', function () {
    Pelanggan::factory()->count(25)->create(['nama_depan' => 'Sama']);

    $component = Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('searchTerms.pelanggan_id', 'Sama');

    expect($component->instance()->searchResults('pelanggan_id'))->toHaveCount(20);
});

test('memilih pelanggan lewat pencarian tetap mereset layanan_pelanggan_id', function () {
    Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('layanan_pelanggan_id', 5)
        ->call('selectSearchable', 'pelanggan_id', $this->pelanggan->id)
        ->assertSet('layanan_pelanggan_id', null);
});

test('pelanggan_id tetap wajib divalidasi meski tanpa native select', function () {
    Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('jenis', JenisTicket::Pemasangan->value)
        ->set('prioritas', PrioritasTicket::Tinggi->value)
        ->set('divisis', [DivisiTicket::Teknisi->value])
        ->set('deskripsi', 'Pemasangan paket 50Mbps di perumahan arsyila.')
        ->call('save')
        ->assertHasErrors(['pelanggan_id' => 'required']);
});

test('livewire show component allows adding follow up notes to ticket history', function () {
    $ticket = Ticket::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('catatanProses', 'Menunggu konfirmasi kedatangan dari pemilik rumah.')
        ->call('simpanCatatan')
        ->assertHasNoErrors();

    $histori = TicketHistori::where('ticket_id', $ticket->id)->latest('id')->first();
    expect($histori)->not->toBeNull()
        ->and($histori->catatan)->toBe('Menunggu konfirmasi kedatangan dari pemilik rumah.');
});
