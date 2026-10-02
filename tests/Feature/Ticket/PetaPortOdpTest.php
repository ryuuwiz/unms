<?php

use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Create as TicketCreate;
use App\Livewire\Ticket\Show;
use App\Models\LayananPelanggan;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\TicketPemasangan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    $this->odp = Odp::factory()->create(['nama_odp' => 'ODP-GRH-02', 'kapasitas_port' => 4]);
    $this->portKosong = OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 1]);
    $this->portRusak = OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 3, 'status' => StatusOdpPort::Rusak]);
    $this->portDipesan = OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 4]);

    $pemakai = Pelanggan::factory()->create(['nama_depan' => 'Siti', 'nama_belakang' => 'Aminah']);
    $this->layananPemakai = LayananPelanggan::factory()->create(['pelanggan_id' => $pemakai->id, 'status' => StatusLayanan::Aktif]);
    $this->portTerpakai = OdpPort::factory()->create([
        'odp_id' => $this->odp->id,
        'nomor_port' => 2,
        'status' => StatusOdpPort::Terpakai,
        'layanan_pelanggan_id' => $this->layananPemakai->id,
    ]);
    $this->layananPemakai->update(['odp_port_id' => $this->portTerpakai->id]);

    $this->ticket = buatTiketPemasanganUntukPeta($this->admin, $this->teknisi);

    $this->tiketLain = buatTiketPemasanganUntukPeta($this->admin, $this->teknisi);
    TicketPemasangan::updateOrCreate(['ticket_id' => $this->tiketLain->id], ['odp_port_id' => $this->portDipesan->id]);
});

function buatTiketPemasanganUntukPeta(User $admin, User $teknisi): Ticket
{
    $layanan = LayananPelanggan::factory()->create([
        'status' => StatusLayanan::Proses,
        // Tanpa ODP di sekitar koordinat ini: tiket memakai jalur "Tanpa ODP dalam jangkauan".
        'latitude' => -6.2,
        'longitude' => 106.8,
        'router_id' => null,
        'ppp_username' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(TicketCreate::class)
        ->set('jenis', JenisTicket::Pemasangan->value)
        ->set('pelanggan_id', $layanan->pelanggan_id)
        ->set('layanan_pelanggan_id', $layanan->id)
        ->set('pic_id', $teknisi->id)
        ->set('deskripsi', 'Pemasangan baru untuk uji Peta Port ODP.')
        ->call('save')
        ->assertHasNoErrors();

    return Ticket::where('layanan_pelanggan_id', $layanan->id)->firstOrFail();
}

test('Peta Port ODP menampilkan semua port ODP beserta status dan ringkasan pemakaian', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->set('odp_id', $this->odp->id)
        ->assertSee('1/4 terpakai')
        ->assertSeeInOrder(['Port 1', 'Kosong', 'Port 2', 'Terpakai', 'Port 3', 'Rusak', 'Port 4', 'Dipesan'])
        ->assertSee($this->layananPemakai->site_id)
        ->assertSee('Siti Aminah')
        ->assertSee($this->layananPemakai->pelanggan->no_reg)
        ->assertSee("Dipesan oleh {$this->tiketLain->nomor_ticket}");
});

test('Teknisi memilih port Kosong lewat Peta Port ODP lalu pilihannya tersimpan saat progress lapangan', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->set('odp_id', $this->odp->id)
        ->call('pilihPort', $this->portKosong->id)
        ->assertHasNoErrors()
        ->assertSet('odp_port_id', $this->portKosong->id)
        ->set('fotoPemasangan', [UploadedFile::fake()->image('pasang.jpg')])
        ->call('simpanProgressLapangan')
        ->assertHasNoErrors();

    expect($this->ticket->fresh()->pemasangan->odp_port_id)->toBe($this->portKosong->id);
});

test('port yang tidak tersedia ditolak saat dipilih lewat Peta Port ODP', function (string $port) {
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->set('odp_id', $this->odp->id)
        ->call('pilihPort', $this->{$port}->id)
        ->assertHasErrors('odp_port_id')
        ->assertSet('odp_port_id', null);
})->with(['portTerpakai', 'portRusak', 'portDipesan']);

test('server menolak menyimpan port Terpakai walau dikirim langsung tanpa Peta Port ODP', function () {
    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $this->ticket])
        ->set('odp_id', $this->odp->id)
        ->set('odp_port_id', $this->portTerpakai->id)
        ->set('fotoPemasangan', [UploadedFile::fake()->image('pasang.jpg')])
        ->call('simpanProgressLapangan')
        ->assertHasErrors('odp_port_id');

    expect($this->ticket->fresh()->pemasangan?->odp_port_id)->toBeNull();
});

test('port milik tiket ini tetap bisa dipilih walau sudah Terpakai setelah Aktivasi', function () {
    TicketPemasangan::updateOrCreate(['ticket_id' => $this->ticket->id], ['odp_port_id' => $this->portKosong->id]);
    $this->portKosong->update(['status' => StatusOdpPort::Terpakai, 'layanan_pelanggan_id' => $this->ticket->layanan_pelanggan_id]);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $this->ticket->fresh()])
        ->call('pilihPort', $this->portKosong->id)
        ->assertHasNoErrors()
        ->assertSet('odp_port_id', $this->portKosong->id);
});

test('tiket Gangguan dan Pencabutan menampilkan Peta Port ODP baca-saja dengan port layanan disorot', function (string $jenis) {
    $tiket = Ticket::factory()->create([
        'jenis' => $jenis,
        'pelanggan_id' => $this->layananPemakai->pelanggan_id,
        'layanan_pelanggan_id' => $this->layananPemakai->id,
        'pic_id' => $this->teknisi->id,
    ]);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $tiket])
        ->assertSee('Port layanan ini')
        ->assertSee('1/4 terpakai')
        ->assertSeeHtml('aria-pressed="true"')
        ->assertDontSeeHtml('wire:click="pilihPort(')
        ->call('pilihPort', $this->portKosong->id)
        ->assertHasErrors('odp_port_id');
})->with([JenisTicket::Gangguan->value, JenisTicket::Pencabutan->value]);

test('tiket yang layanannya tanpa port tidak menampilkan Peta Port ODP', function () {
    $layanan = LayananPelanggan::factory()->create(['status' => StatusLayanan::Aktif, 'odp_port_id' => null]);
    $tiket = Ticket::factory()->gangguan()->create([
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'pic_id' => $this->teknisi->id,
    ]);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $tiket])
        ->assertDontSee('Port layanan ini');
});

test('port tidak bisa diganti setelah Aktivasi, tetapi Teknisi tetap bisa menyimpan foto', function () {
    TicketPemasangan::updateOrCreate(['ticket_id' => $this->ticket->id], ['odp_port_id' => $this->portKosong->id, 'diaktivasi_pada' => now()]);
    $portLain = OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 5]);

    Livewire::actingAs($this->teknisi)
        ->test(Show::class, ['ticket' => $this->ticket->fresh()])
        ->call('pilihPort', $portLain->id)
        ->assertHasErrors('odp_port_id')
        ->set('odp_port_id', $portLain->id)
        ->set('fotoSpeedtest', [UploadedFile::fake()->image('speed.jpg')])
        ->call('simpanProgressLapangan');

    $tiket = $this->ticket->fresh();
    expect($tiket->pemasangan->odp_port_id)->toBe($this->portKosong->id)
        ->and($tiket->getMedia('foto_speedtest'))->toHaveCount(1);
});

test('port tidak bisa dipilih pada Ticket Pemasangan yang sudah Batal', function () {
    $this->ticket->update(['status' => StatusTicket::Batal]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $this->ticket->fresh()])
        ->set('odp_id', $this->odp->id)
        ->call('pilihPort', $this->portKosong->id)
        ->assertHasErrors('odp_port_id');
});

test('Peta Port ODP baca-saja tetap tampil di Pemasangan teraktivasi setelah Teknisi selesai', function () {
    $layanan = $this->ticket->layananPelanggan;
    $this->portKosong->update(['status' => StatusOdpPort::Terpakai, 'layanan_pelanggan_id' => $layanan->id]);
    $layanan->update(['odp_port_id' => $this->portKosong->id]);
    TicketPemasangan::updateOrCreate(['ticket_id' => $this->ticket->id], ['odp_port_id' => $this->portKosong->id, 'diaktivasi_pada' => now()]);
    DB::table('ticket_divisi')->where('ticket_id', $this->ticket->id)->where('divisi', 'teknisi')->update(['status' => 'selesai']);

    foreach ([$this->admin, $this->teknisi] as $user) {
        Livewire::actingAs($user)
            ->test(Show::class, ['ticket' => $this->ticket->fresh()])
            ->assertSee('Port layanan ini');
    }
});
