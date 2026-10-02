<?php

use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
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
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    $this->odp = Odp::factory()->create(['nama_odp' => 'ODP-GRH-02']);
    $this->pelanggan = Pelanggan::factory()->create(['nama_depan' => 'Siti', 'nama_belakang' => 'Aminah']);
    $this->layanan = LayananPelanggan::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'status' => StatusLayanan::Aktif]);
    $this->port = OdpPort::factory()->create([
        'odp_id' => $this->odp->id,
        'nomor_port' => 5,
        'status' => StatusOdpPort::Terpakai,
        'layanan_pelanggan_id' => $this->layanan->id,
    ]);
    $this->layanan->update(['odp_port_id' => $this->port->id]);

    $this->labelTercetak = null;
    View::composer('pdf.label-port', function ($view) {
        $this->labelTercetak = $view->getData()['labels'];
    });
});

function tiketLabel(LayananPelanggan $layanan, User $pic, JenisTicket $jenis = JenisTicket::Gangguan): Ticket
{
    return Ticket::factory()->create([
        'jenis' => $jenis,
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
        'pic_id' => $pic->id,
    ]);
}

test('Label Port dari tiket berupa PDF berisi ODP, port, nama pelanggan, No. Registrasi, dan Site ID', function () {
    $tiket = tiketLabel($this->layanan, $this->teknisi);

    $this->actingAs($this->teknisi)
        ->get(route('ticket.label-port', $tiket))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($this->labelTercetak)->toHaveCount(1)
        ->and($this->labelTercetak[0])->toBe([
            'odp' => 'ODP-GRH-02',
            'port' => 5,
            'nama' => 'Siti Aminah',
            'no_reg' => $this->pelanggan->no_reg,
            'site_id' => $this->layanan->site_id,
        ]);
});

test('Label Port bisa dicetak untuk port yang baru Dipesan Ticket Pemasangan sebelum Aktivasi', function () {
    $layananBaru = LayananPelanggan::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'status' => StatusLayanan::Proses, 'odp_port_id' => null]);
    $portDipesan = OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 6]);
    $tiket = tiketLabel($layananBaru, $this->teknisi, JenisTicket::Pemasangan);
    TicketPemasangan::updateOrCreate(['ticket_id' => $tiket->id], ['odp_port_id' => $portDipesan->id]);

    $this->actingAs($this->teknisi)->get(route('ticket.label-port', $tiket))->assertOk();

    expect($this->labelTercetak[0]['port'])->toBe(6)
        ->and($this->labelTercetak[0]['site_id'])->toBe($layananBaru->site_id);
});

test('Label Port tidak tersedia untuk tiket tanpa port dan tiket Pencabutan', function () {
    $layananTanpaPort = LayananPelanggan::factory()->create(['status' => StatusLayanan::Aktif, 'odp_port_id' => null]);

    $this->actingAs($this->admin)->get(route('ticket.label-port', tiketLabel($layananTanpaPort, $this->teknisi)))->assertNotFound();
    $this->actingAs($this->admin)->get(route('ticket.label-port', tiketLabel($this->layanan, $this->teknisi, JenisTicket::Pencabutan)))->assertNotFound();
});

test('Teknisi yang bukan PIC tidak boleh mencetak Label Port tiket', function () {
    $teknisiLain = User::factory()->create(['status' => UserStatus::Active]);
    $teknisiLain->assignRole('teknisi');

    $this->actingAs($teknisiLain)->get(route('ticket.label-port', tiketLabel($this->layanan, $this->teknisi)))->assertForbidden();
});

test('tombol Cetak Label Port hanya tampil di tiket yang punya port dan bukan Pencabutan', function () {
    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => tiketLabel($this->layanan, $this->teknisi)])
        ->assertSee('Cetak Label Port');

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => tiketLabel($this->layanan, $this->teknisi, JenisTicket::Pencabutan)])
        ->assertDontSee('Cetak Label Port');
});

test('Label Port tidak tersedia dari Ticket Pemasangan Batal yang port-nya hanya pernah Dipesan', function () {
    $layananBaru = LayananPelanggan::factory()->create(['pelanggan_id' => $this->pelanggan->id, 'status' => StatusLayanan::Proses, 'odp_port_id' => null]);
    $port = OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 7]);
    $tiket = tiketLabel($layananBaru, $this->teknisi, JenisTicket::Pemasangan);
    TicketPemasangan::updateOrCreate(['ticket_id' => $tiket->id], ['odp_port_id' => $port->id]);
    $tiket->update(['status' => StatusTicket::Batal]);

    $this->actingAs($this->admin)->get(route('ticket.label-port', $tiket))->assertNotFound();
});
