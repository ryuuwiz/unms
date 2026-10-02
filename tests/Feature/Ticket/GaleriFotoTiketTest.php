<?php

use App\Enums\StatusLayanan;
use App\Enums\Ticket\JenisTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Show;
use App\Models\LayananPelanggan;
use App\Models\Ticket;
use App\Models\TicketHistori;
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

    $this->layanan = LayananPelanggan::factory()->create(['status' => StatusLayanan::Proses]);
});

function tiketGaleri(LayananPelanggan $layanan, JenisTicket $jenis): Ticket
{
    return Ticket::factory()->create([
        'jenis' => $jenis,
        'pelanggan_id' => $layanan->pelanggan_id,
        'layanan_pelanggan_id' => $layanan->id,
    ]);
}

test('Galeri Foto Tiket mengelompokkan Foto Pengerjaan Lapangan, Foto Kendala, dan foto Histori Tiket', function () {
    $tiket = tiketGaleri($this->layanan, JenisTicket::Pemasangan);
    $pemasangan = $tiket->addMedia(UploadedFile::fake()->image('kabel.jpg'))->toMediaCollection('foto_pemasangan');
    $kendala = $tiket->addMedia(UploadedFile::fake()->image('kendala.jpg'))->toMediaCollection('foto_kendala');
    $histori = TicketHistori::create([
        'ticket_id' => $tiket->id,
        'status_lama' => $tiket->status,
        'status_baru' => $tiket->status,
        'catatan' => 'Redaman OPM normal.',
        'oleh_pengguna_id' => $this->admin->id,
    ]);
    $pengerjaan = $histori->addMedia(UploadedFile::fake()->image('opm.jpg'))->toMediaCollection('foto_pengerjaan');

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $tiket])
        ->assertSee('Galeri Foto Tiket')
        ->assertSeeInOrder(['Speedtest', 'Belum ada foto', 'Tanda Tangan MOU', 'Belum ada foto', 'Bukti Pemasangan', 'Foto Kendala', 'Foto Bukti Pengerjaan'])
        ->assertSee($pemasangan->getUrl())
        ->assertSee($kendala->getUrl())
        ->assertSee($pengerjaan->getUrl())
        ->assertSee('Lihat foto kendala')
        ->assertSee('Lihat foto')
        ->assertDontSeeHtml('alt="Foto Kendala"')
        ->assertDontSeeHtml('alt="Bukti Pengerjaan"');
});

test('kategori wajib Speedtest dan MOU tidak tampil di tiket selain Ticket Pemasangan', function () {
    $tiket = tiketGaleri($this->layanan, JenisTicket::Gangguan);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $tiket])
        ->assertDontSee('Tanda Tangan MOU')
        ->assertSee('Belum ada foto pada tiket ini');
});

test('Teknisi bisa membatalkan foto terpilih sebelum disimpan', function () {
    $tiket = tiketGaleri($this->layanan, JenisTicket::Pemasangan);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['ticket' => $tiket])
        ->set('fotoSpeedtest', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->call('batalkanFotoTerpilih', 'fotoSpeedtest', 0)
        ->assertCount('fotoSpeedtest', 1)
        ->set('fotoMou', UploadedFile::fake()->image('mou.jpg'))
        ->call('batalkanFotoTerpilih', 'fotoMou')
        ->assertSet('fotoMou', null);
});
