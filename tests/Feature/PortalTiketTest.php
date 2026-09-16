<?php

use App\Actions\Ticket\UbahStatusTicketAction;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\Ticket\SumberTicket;
use App\Livewire\Portal\NotificationBell;
use App\Livewire\Portal\Tiket\Create;
use App\Livewire\Portal\Tiket\Index;
use App\Livewire\Portal\Tiket\Show;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketBaruDariPortalNotification;
use App\Notifications\TicketStatusBerubahPelangganNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─────────────────────────────────────────────────────────────
// Helper: setup pelanggan dengan akun portal dan layanan aktif
// ─────────────────────────────────────────────────────────────
function setupPortalPelanggan(): array
{
    $pelanggan = Pelanggan::factory()->create();
    $akun = $pelanggan->akunPelanggan; // dibuat otomatis saat factory Pelanggan
    $layanan = LayananPelanggan::factory()->for($pelanggan)->create();

    return [$pelanggan, $akun, $layanan];
}

// ─────────────────────────────────────────────────────────────
// Portal Tiket Index
// ─────────────────────────────────────────────────────────────
describe('Portal Tiket Index', function () {
    it('menampilkan daftar tiket milik pelanggan', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();

        $ticket = Ticket::factory()->dariPortal()->create(['pelanggan_id' => $pelanggan->id]);
        DB::table('ticket_divisi')->insert(['ticket_id' => $ticket->id, 'divisi' => DivisiTicket::Teknisi->value]);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Index::class)
            ->assertSee($ticket->nomor_ticket);
    });

    it('tidak menampilkan tiket milik pelanggan lain', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();
        $pelangganLain = Pelanggan::factory()->create();
        $ticketLain = Ticket::factory()->create(['pelanggan_id' => $pelangganLain->id]);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Index::class)
            ->assertDontSee($ticketLain->nomor_ticket);
    });
});

// ─────────────────────────────────────────────────────────────
// Portal Tiket Create
// ─────────────────────────────────────────────────────────────
describe('Portal Tiket Create', function () {
    it('dapat membuat tiket gangguan dengan auto-mapping divisi NOC+Teknisi', function () {
        Notification::fake();
        [$pelanggan, $akun, $layanan] = setupPortalPelanggan();

        $staf = User::factory()->create();
        $staf->givePermissionTo('ticket.lihat');

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Create::class)
            ->set('jenis', 'gangguan')
            ->set('layanan_pelanggan_id', $layanan->id)
            ->set('deskripsi', 'Koneksi internet tidak bisa sama sekali sejak tadi malam.')
            ->call('simpan');

        $ticket = Ticket::where('pelanggan_id', $pelanggan->id)->latest()->first();

        expect($ticket)->not->toBeNull()
            ->and($ticket->sumber)->toBe(SumberTicket::Portal)
            ->and($ticket->jenis)->toBe(JenisTicket::Gangguan)
            ->and($ticket->prioritas)->toBe(PrioritasTicket::Sedang)
            ->and($ticket->dibuat_oleh)->toBeNull();

        $divisis = DB::table('ticket_divisi')->where('ticket_id', $ticket->id)->pluck('divisi')->sort()->values()->toArray();
        expect($divisis)->toEqual([DivisiTicket::Noc->value, DivisiTicket::Teknisi->value]);

        Notification::assertSentTo($staf, TicketBaruDariPortalNotification::class);
    });

    it('dapat membuat tiket pencabutan dengan auto-mapping divisi Teknisi+CS', function () {
        Notification::fake();
        [$pelanggan, $akun, $layanan] = setupPortalPelanggan();

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Create::class)
            ->set('jenis', 'pencabutan')
            ->set('layanan_pelanggan_id', $layanan->id)
            ->set('deskripsi', 'Saya ingin mencabut layanan internet.')
            ->call('simpan');

        $ticket = Ticket::where('pelanggan_id', $pelanggan->id)->latest()->first();
        $divisis = DB::table('ticket_divisi')->where('ticket_id', $ticket->id)->pluck('divisi')->sort()->values()->toArray();

        expect($ticket->prioritas)->toBe(PrioritasTicket::Rendah)
            ->and($divisis)->toEqual([DivisiTicket::CustomerService->value, DivisiTicket::Teknisi->value]);
    });

    it('jenis pemasangan tidak tersedia di portal', function () {
        [$pelanggan, $akun, $layanan] = setupPortalPelanggan();

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Create::class)
            ->set('jenis', 'pemasangan')
            ->set('layanan_pelanggan_id', $layanan->id)
            ->set('deskripsi', 'Mau pasang baru.')
            ->call('simpan')
            ->assertHasErrors(['jenis']);
    });

    it('validasi: deskripsi wajib minimal 5 karakter', function () {
        [$pelanggan, $akun, $layanan] = setupPortalPelanggan();

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Create::class)
            ->set('jenis', 'gangguan')
            ->set('layanan_pelanggan_id', $layanan->id)
            ->set('deskripsi', 'Oops')
            ->call('simpan')
            ->assertHasErrors(['deskripsi']);
    });

    it('menyimpan lampiran foto kendala ke media library saat diunggah', function () {
        Storage::fake('public');
        Notification::fake();
        [$pelanggan, $akun, $layanan] = setupPortalPelanggan();

        $foto = UploadedFile::fake()->image('kendala.jpg');

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Create::class)
            ->set('jenis', 'gangguan')
            ->set('layanan_pelanggan_id', $layanan->id)
            ->set('deskripsi', 'Koneksi internet putus total sejak pagi.')
            ->set('fotoKendala', $foto)
            ->call('simpan');

        $ticket = Ticket::where('pelanggan_id', $pelanggan->id)->latest()->first();

        expect($ticket)->not->toBeNull()
            ->and($ticket->getFirstMedia('foto_kendala'))->not->toBeNull();
    });

    it('tidak bisa pilih layanan milik pelanggan lain', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();
        $pelangganLain = Pelanggan::factory()->create();
        $layananLain = LayananPelanggan::factory()->for($pelangganLain)->create();

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Create::class)
            ->set('jenis', 'gangguan')
            ->set('layanan_pelanggan_id', $layananLain->id)
            ->set('deskripsi', 'Gangguan tidak bisa connect ke internet.')
            ->call('simpan')
            ->assertStatus(403);
    });
});

// ─────────────────────────────────────────────────────────────
// Portal Tiket Show
// ─────────────────────────────────────────────────────────────
describe('Portal Tiket Show', function () {
    it('menampilkan detail tiket milik pelanggan', function () {
        [$pelanggan, $akun, $layanan] = setupPortalPelanggan();

        $ticket = Ticket::factory()->dariPortal()->create([
            'pelanggan_id' => $pelanggan->id,
            'layanan_pelanggan_id' => $layanan->id,
        ]);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Show::class, ['ticket' => $ticket])
            ->assertSee($ticket->nomor_ticket)
            ->assertSee($ticket->deskripsi);
    });

    it('tidak bisa melihat tiket milik pelanggan lain', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();
        $pelangganLain = Pelanggan::factory()->create();
        $ticketLain = Ticket::factory()->dariPortal()->create(['pelanggan_id' => $pelangganLain->id]);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Show::class, ['ticket' => $ticketLain])
            ->assertStatus(403);
    });

    it('histori internal tidak terlihat oleh pelanggan', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();

        $ticket = Ticket::factory()->dariPortal()->create(['pelanggan_id' => $pelanggan->id]);
        $ticket->histori()->create([
            'status_lama' => StatusTicket::Baru,
            'status_baru' => StatusTicket::Baru,
            'catatan' => 'CATATAN INTERNAL RAHASIA',
            'is_internal' => true,
            'oleh_pengguna_id' => null,
        ]);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Show::class, ['ticket' => $ticket])
            ->assertDontSee('CATATAN INTERNAL RAHASIA');
    });
});

// ─────────────────────────────────────────────────────────────
// Batalkan Tiket
// ─────────────────────────────────────────────────────────────
describe('Portal Batalkan Tiket', function () {
    it('pelanggan dapat membatalkan tiket yang masih Baru', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();

        $ticket = Ticket::factory()->dariPortal()->create([
            'pelanggan_id' => $pelanggan->id,
            'status' => StatusTicket::Baru,
        ]);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Show::class, ['ticket' => $ticket])
            ->set('alasanBatalkan', 'Masalah sudah teratasi sendiri setelah restart modem.')
            ->call('batalkanTiket');

        expect($ticket->fresh()->status)->toBe(StatusTicket::Batal);
    });

    it('pelanggan tidak bisa membatalkan tiket yang sudah Diproses', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();

        $ticket = Ticket::factory()->dariPortal()->diproses()->create(['pelanggan_id' => $pelanggan->id]);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Show::class, ['ticket' => $ticket])
            ->set('alasanBatalkan', 'Coba batalkan.')
            ->call('batalkanTiket');

        expect($ticket->fresh()->status)->toBe(StatusTicket::Diproses);
    });

    it('alasan pembatalan wajib minimal 5 karakter', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();
        $ticket = Ticket::factory()->dariPortal()->create(['pelanggan_id' => $pelanggan->id]);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(Show::class, ['ticket' => $ticket])
            ->set('alasanBatalkan', 'Ok')
            ->call('batalkanTiket')
            ->assertHasErrors(['alasanBatalkan']);
    });
});

// ─────────────────────────────────────────────────────────────
// Notifikasi
// ─────────────────────────────────────────────────────────────
describe('Notifikasi Tiket Portal', function () {
    it('pelanggan menerima notifikasi saat status tiket portal berubah', function () {
        Notification::fake();
        [$pelanggan, $akun] = setupPortalPelanggan();

        $staf = User::factory()->create();
        $staf->assignRole('admin');
        $ticket = Ticket::factory()->dariPortal()->create(['pelanggan_id' => $pelanggan->id]);

        $action = app(UbahStatusTicketAction::class);
        $action->execute($ticket, StatusTicket::Diproses, $staf);

        Notification::assertSentTo($akun, TicketStatusBerubahPelangganNotification::class);
    });

    it('bell notifikasi menampilkan jumlah belum dibaca dan dapat menandai semua dibaca', function () {
        [$pelanggan, $akun] = setupPortalPelanggan();
        $ticket = Ticket::factory()->dariPortal()->create(['pelanggan_id' => $pelanggan->id]);

        $akun->notify(new TicketStatusBerubahPelangganNotification(
            ticket: $ticket,
            statusLama: StatusTicket::Baru,
            statusBaru: StatusTicket::Diproses,
        ));

        expect($akun->unreadNotifications()->count())->toBe(1);

        Livewire::actingAs($akun, 'pelanggan')
            ->test(NotificationBell::class)
            ->assertSee('1 baru')
            ->call('tandaiSemuaDibaca');

        expect($akun->fresh()->unreadNotifications()->count())->toBe(0);
    });
});
