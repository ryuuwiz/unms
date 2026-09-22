<?php

use App\Enums\StatusInvoice;
use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Enums\Ticket\DivisiTicket;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusDivisiTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Create as TicketCreate;
use App\Livewire\Ticket\Show;
use App\Models\Invoice;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');

    $this->cs = User::factory()->create(['status' => UserStatus::Active]);
    $this->cs->assignRole('customer_service');

    $this->pelanggan = Pelanggan::factory()->create();
    $this->profil = ProfilBandwidth::factory()->create();
    $this->paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $this->profil->id]);
    $this->layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'status' => StatusLayanan::Proses,
        'router_id' => null,
        'ppp_username' => null,
    ]);

    $this->router = Router::factory()->online()->create();
    $this->ipPool = IpPool::factory()->create(['router_id' => $this->router->id]);

    $this->odp = Odp::factory()->create();
    $this->odpPort = OdpPort::factory()->create(['odp_id' => $this->odp->id]);

    $this->invoicePertama = Invoice::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'layanan_pelanggan_id' => $this->layanan->id,
        'periode_tagihan' => null,
        'status' => StatusInvoice::MenungguPembayaran,
    ]);
});

function buatTicketPemasangan(User $admin, Pelanggan $pelanggan, LayananPelanggan $layanan, User $teknisi): Ticket
{
    Livewire::actingAs($admin)
        ->test(TicketCreate::class)
        ->set('jenis', JenisTicket::Pemasangan->value)
        ->set('pelanggan_id', $pelanggan->id)
        ->set('layanan_pelanggan_id', $layanan->id)
        ->set('pic_id', $teknisi->id)
        ->set('deskripsi', 'Pemasangan baru merujuk layanan yang sudah didaftarkan.')
        ->call('save')
        ->assertHasNoErrors();

    return Ticket::where('layanan_pelanggan_id', $layanan->id)->firstOrFail();
}

test('membuat ticket pemasangan yang merujuk layanan otomatis assign keempat divisi wajib', function () {
    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);

    expect($ticket->getDivisValues())->toEqualCanonicalizing([
        DivisiTicket::Teknisi->value, DivisiTicket::Noc->value, DivisiTicket::CustomerService->value, DivisiTicket::Admin->value,
    ]);

    foreach (Ticket::DIVISI_WAJIB_PEMASANGAN as $divisi) {
        expect($ticket->statusDivisi($divisi))->toBe(StatusDivisiTicket::Belum);
    }
});

test('ticket pemasangan tanpa layanan_pelanggan_id tetap hanya divisi teknisi (alur lama)', function () {
    Livewire::actingAs($this->admin)
        ->test(TicketCreate::class)
        ->set('jenis', JenisTicket::Pemasangan->value)
        ->set('pelanggan_id', $this->pelanggan->id)
        ->set('deskripsi', 'Pemasangan prospek baru, belum ada layanan.')
        ->call('save')
        ->assertHasNoErrors();

    $ticket = Ticket::where('pelanggan_id', $this->pelanggan->id)->whereNull('layanan_pelanggan_id')->firstOrFail();
    expect($ticket->getDivisValues())->toBe([DivisiTicket::Teknisi->value]);
});

test('teknisi menyimpan progress lapangan mengunci ODP+Port dan menandai divisi Progress', function () {
    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('odp_id', $this->odp->id)
        ->set('odp_port_id', $this->odpPort->id)
        ->set('fotoPemasangan', [UploadedFile::fake()->image('pasang1.jpg')])
        ->call('simpanProgressLapangan')
        ->assertHasNoErrors();

    $ticket->refresh();
    expect($ticket->statusDivisi(DivisiTicket::Teknisi))->toBe(StatusDivisiTicket::Progress)
        ->and($ticket->pemasangan?->odp_port_id)->toBe($this->odpPort->id)
        ->and($ticket->getMedia('foto_pemasangan'))->toHaveCount(1);
});

test('teknisi lain yang bukan PIC tidak bisa membuka/mengisi progress lapangan tiket ini', function () {
    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);
    $teknisiLain = User::factory()->create(['status' => UserStatus::Active]);
    $teknisiLain->assignRole('teknisi');

    // TicketPolicy::view() sudah menolak Teknisi yang bukan PIC tiket ini sejak mount().
    Livewire::actingAs($teknisiLain)
        ->test(Show::class, ['ticket' => $ticket])
        ->assertForbidden();
});

test('aktivasi tidak bisa dijalankan sebelum teknisi mengisi progress lapangan', function () {
    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $ticket])
        ->call('openAktivasiModal');

    expect($ticket->fresh()->siapDiaktivasi())->toBeFalse();
    $this->layanan->refresh();
    expect($this->layanan->router_id)->toBeNull();
});

test('non-noc tanpa permission aktivasi tidak bisa menjalankan Aktivasi Pemasangan', function () {
    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('odp_id', $this->odp->id)
        ->set('odp_port_id', $this->odpPort->id)
        ->set('fotoPemasangan', [UploadedFile::fake()->image('pasang1.jpg')])
        ->call('simpanProgressLapangan');

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->set('aktivasiRouterId', $this->router->id)
        ->set('aktivasiIpPoolId', $this->ipPool->id)
        ->call('prosesAktivasi')
        ->assertForbidden();
});

test('NOC dapat menjalankan Aktivasi Pemasangan: mengisi router, ip pool, ppp username, dan mengikat port ODP', function () {
    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('odp_id', $this->odp->id)
        ->set('odp_port_id', $this->odpPort->id)
        ->set('fotoPemasangan', [UploadedFile::fake()->image('pasang1.jpg')])
        ->call('simpanProgressLapangan');

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->set('aktivasiRouterId', $this->router->id)
        ->set('aktivasiIpPoolId', $this->ipPool->id)
        ->call('prosesAktivasi')
        ->assertHasNoErrors();

    $this->layanan->refresh();
    expect($this->layanan->router_id)->toBe($this->router->id)
        ->and($this->layanan->ip_pool_id)->toBe($this->ipPool->id)
        ->and($this->layanan->ppp_username)->not->toBeNull()
        ->and($this->layanan->ppp_username)->toMatch('/^'.preg_quote($this->pelanggan->no_reg, '/').'_[0-9]{5}$/')
        ->and($this->layanan->odp_port_id)->toBe($this->odpPort->id);

    $this->odpPort->refresh();
    expect($this->odpPort->status)->toBe(StatusOdpPort::Terpakai)
        ->and($this->odpPort->layanan_pelanggan_id)->toBe($this->layanan->id);

    expect($ticket->fresh()->pemasangan?->sudahDiaktivasi())->toBeTrue();
});

test('aktivasi ditolak jika pelanggan sudah punya layanan aktif dengan router dan paket yang sama', function () {
    LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'paket_layanan_id' => $this->paket->id,
        'router_id' => $this->router->id,
        'status' => StatusLayanan::Aktif,
    ]);

    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('odp_id', $this->odp->id)
        ->set('odp_port_id', $this->odpPort->id)
        ->set('fotoPemasangan', [UploadedFile::fake()->image('pasang1.jpg')])
        ->call('simpanProgressLapangan');

    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->set('aktivasiRouterId', $this->router->id)
        ->set('aktivasiIpPoolId', $this->ipPool->id)
        ->call('prosesAktivasi')
        ->assertHasErrors(['aktivasiRouterId']);

    expect($this->layanan->fresh()->router_id)->toBeNull();
});

test('admin tidak bisa menandai divisi Admin selesai sebelum invoice pertama lunas', function () {
    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $ticket])
        ->call('tandaiDivisiSelesai', DivisiTicket::Admin->value);

    expect($ticket->fresh()->statusDivisi(DivisiTicket::Admin))->toBe(StatusDivisiTicket::Belum);
});

test('alur lengkap: keempat divisi selesai memicu status ticket otomatis Selesai', function () {
    $ticket = buatTicketPemasangan($this->admin, $this->pelanggan, $this->layanan, $this->teknisi);

    // Teknisi tahap 1
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket])
        ->set('odp_id', $this->odp->id)
        ->set('odp_port_id', $this->odpPort->id)
        ->set('fotoPemasangan', [UploadedFile::fake()->image('pasang1.jpg')])
        ->call('simpanProgressLapangan');

    // NOC aktivasi
    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->set('aktivasiRouterId', $this->router->id)
        ->set('aktivasiIpPoolId', $this->ipPool->id)
        ->call('prosesAktivasi')
        ->assertHasNoErrors();

    // Teknisi tahap 2 + selesai
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->set('fotoSpeedtest', [UploadedFile::fake()->image('speedtest.jpg')])
        ->set('fotoMou', UploadedFile::fake()->image('mou.jpg'))
        ->set('fotoBersama', [UploadedFile::fake()->image('bersama.jpg')])
        ->call('simpanFotoTahapDua')
        ->assertHasNoErrors()
        ->call('tandaiDivisiSelesai', DivisiTicket::Teknisi->value);

    expect($ticket->fresh()->statusDivisi(DivisiTicket::Teknisi))->toBe(StatusDivisiTicket::Selesai);

    // NOC selesai
    Livewire::actingAs($this->noc)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->call('tandaiDivisiSelesai', DivisiTicket::Noc->value);

    // CS selesai
    Livewire::actingAs($this->cs)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->call('tandaiDivisiSelesai', DivisiTicket::CustomerService->value);

    // Belum semua selesai karena Admin masih digate pembayaran
    expect($ticket->fresh()->status)->not->toBe(StatusTicket::Selesai);

    // Pelanggan bayar invoice pertama
    $this->invoicePertama->update(['status' => StatusInvoice::Lunas, 'tanggal_lunas' => now()]);

    // Admin selesai -- ini yang terakhir, harus auto-derive status ticket ke Selesai
    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $ticket->fresh()])
        ->call('tandaiDivisiSelesai', DivisiTicket::Admin->value);

    $ticket->refresh();
    expect($ticket->statusDivisi(DivisiTicket::Admin))->toBe(StatusDivisiTicket::Selesai)
        ->and($ticket->status)->toBe(StatusTicket::Selesai)
        ->and($ticket->perlu_aktivasi_manual)->toBeFalse();
});
