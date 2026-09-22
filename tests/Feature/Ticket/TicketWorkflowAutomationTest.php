<?php

use App\Actions\Ticket\UbahStatusTicketAction;
use App\Enums\StatusLayanan;
use App\Enums\StatusPelanggan;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\LayananPelanggan\Create as LayananCreate;
use App\Livewire\Ticket\Create as TicketCreate;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Ticket;
use App\Models\TicketHistori;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');
});

it('menurunkan status pelanggan dari seluruh layanannya', function () {
    $pelanggan = Pelanggan::factory()->belumTerpasang()->create();
    $a = LayananPelanggan::factory()->proses()->create(['pelanggan_id' => $pelanggan->id]);
    $b = LayananPelanggan::factory()->proses()->create(['pelanggan_id' => $pelanggan->id]);

    expect($pelanggan->fresh()->status)->toBe(StatusPelanggan::BelumTerpasang);

    $a->update(['status' => StatusLayanan::Aktif]);
    expect($pelanggan->fresh()->status)->toBe(StatusPelanggan::Aktif);

    $b->update(['status' => StatusLayanan::Suspend]);
    expect($pelanggan->fresh()->status)->toBe(StatusPelanggan::Aktif);

    $a->update(['status' => StatusLayanan::Suspend]);
    expect($pelanggan->fresh()->status)->toBe(StatusPelanggan::Expired);

    $a->update(['status' => StatusLayanan::Berhenti]);
    $b->update(['status' => StatusLayanan::Berhenti]);
    expect($pelanggan->fresh()->status)->toBe(StatusPelanggan::Off);
});

it('menggerakkan status tahap pemasangan pelanggan lewat tiket Pemasangan', function () {
    $pelanggan = Pelanggan::factory()->belumTerpasang()->create();

    Livewire::actingAs($this->admin)->test(TicketCreate::class)
        ->set('jenis', 'pemasangan')
        ->set('pelanggan_id', $pelanggan->id)
        ->set('deskripsi', 'Pasang baru rumah')
        ->call('save')
        ->assertHasNoErrors();
    expect($pelanggan->fresh()->status)->toBe(StatusPelanggan::ReqPemasangan);

    $ticket = Ticket::where('pelanggan_id', $pelanggan->id)->firstOrFail();
    $ticket->update(['status' => StatusTicket::MenungguKonfirmasi]);
    app(UbahStatusTicketAction::class)->execute($ticket, StatusTicket::Selesai, $this->admin);

    expect($pelanggan->fresh()->status)->toBe(StatusPelanggan::PemasanganSelesai)
        ->and($ticket->fresh()->perlu_aktivasi_manual)->toBeTrue();
});

it('mengembalikan pelanggan ke BelumTerpasang saat tiket Pemasangan dibatalkan, tetapi tidak menimpa pelanggan Aktif', function () {
    $baru = Pelanggan::factory()->create(['status' => StatusPelanggan::ReqPemasangan]);
    $aktif = Pelanggan::factory()->create(['status' => StatusPelanggan::Aktif]);

    foreach ([$baru, $aktif] as $pelanggan) {
        $ticket = Ticket::factory()->pemasangan()->create(['pelanggan_id' => $pelanggan->id]);
        app(UbahStatusTicketAction::class)->execute($ticket, StatusTicket::Batal, $this->admin, 'Tidak ada jangkauan');
    }

    expect($baru->fresh()->status)->toBe(StatusPelanggan::BelumTerpasang)
        ->and($aktif->fresh()->status)->toBe(StatusPelanggan::Aktif);
});

it('menghentikan layanan terkait saat tiket Pencabutan selesai, termasuk oleh NOC', function () {
    Queue::fake();

    $pelanggan = Pelanggan::factory()->create();
    $dicabut = LayananPelanggan::factory()->create(['pelanggan_id' => $pelanggan->id]);
    $tetap = LayananPelanggan::factory()->create(['pelanggan_id' => $pelanggan->id]);
    $ticket = Ticket::factory()->create([
        'jenis' => 'pencabutan',
        'status' => StatusTicket::MenungguKonfirmasi,
        'pelanggan_id' => $pelanggan->id,
        'layanan_pelanggan_id' => $dicabut->id,
    ]);

    app(UbahStatusTicketAction::class)->execute($ticket, StatusTicket::Selesai, $this->noc);

    expect($dicabut->fresh()->status)->toBe(StatusLayanan::Berhenti)
        ->and($tetap->fresh()->status)->toBe(StatusLayanan::Aktif)
        ->and($pelanggan->fresh()->status)->toBe(StatusPelanggan::Aktif);
});

it('mewajibkan layanan terkait pada tiket Pencabutan dan Pindah Alamat', function () {
    $pelanggan = Pelanggan::factory()->create();

    foreach (['pencabutan', 'pindah_alamat'] as $jenis) {
        Livewire::actingAs($this->admin)->test(TicketCreate::class)
            ->set('jenis', $jenis)
            ->set('pelanggan_id', $pelanggan->id)
            ->set('deskripsi', 'Permohonan pelanggan')
            ->call('save')
            ->assertHasErrors(['layanan_pelanggan_id']);
    }
});

it('menautkan tiket Pemasangan selesai ke layanan yang dibuat darinya dan mematikan penanda', function () {
    Queue::fake();
    $pelanggan = Pelanggan::factory()->create(['status' => StatusPelanggan::PemasanganSelesai]);
    $ticket = Ticket::factory()->pemasangan()->create([
        'status' => StatusTicket::Selesai,
        'pelanggan_id' => $pelanggan->id,
        'perlu_aktivasi_manual' => true,
    ]);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => ProfilBandwidth::factory()->create()->id]);

    Livewire::actingAs($this->admin)
        ->withQueryParams(['ticket_id' => $ticket->id])
        ->test(LayananCreate::class, ['pelanggan' => $pelanggan])
        ->assertSet('pelanggan_id', $pelanggan->id)
        ->assertSet('ticket_id', $ticket->id)
        ->set('paket_layanan_id', $paket->id)
        ->call('nextStep')
        ->set('jenis_tagihan_pertama', 'full_bulan')
        ->call('save')
        ->assertHasNoErrors();

    $layanan = LayananPelanggan::where('pelanggan_id', $pelanggan->id)->firstOrFail();
    expect($ticket->fresh()->layanan_pelanggan_id)->toBe($layanan->id)
        ->and($ticket->fresh()->perlu_aktivasi_manual)->toBeFalse();
});

it('menampilkan kebutuhan invoice Pindah Alamat sampai invoice manual dibuat', function () {
    $layanan = LayananPelanggan::factory()->create();
    $ticket = Ticket::factory()->create([
        'jenis' => 'pindah_alamat',
        'status' => StatusTicket::Selesai,
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
    ]);
    TicketHistori::create([
        'ticket_id' => $ticket->id,
        'status_lama' => StatusTicket::MenungguKonfirmasi,
        'status_baru' => StatusTicket::Selesai,
        'oleh_pengguna_id' => $this->admin->id,
    ]);

    expect($ticket->perluInvoicePindahAlamat())->toBeTrue();

    Invoice::factory()->create([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'periode_tagihan' => null,
    ]);

    expect($ticket->perluInvoicePindahAlamat())->toBeFalse();
});
