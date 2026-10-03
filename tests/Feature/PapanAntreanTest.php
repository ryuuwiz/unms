<?php

use App\Enums\Ticket\JenisTicket;
use App\Enums\Ticket\StatusTicket;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tiketPapan(array $atribut = [], string $nama = 'Budi Santoso'): Ticket
{
    [$depan, $belakang] = explode(' ', $nama);
    $pelanggan = Pelanggan::factory()->create(['nama_depan' => $depan, 'nama_belakang' => $belakang]);

    return Ticket::factory()->create(['pelanggan_id' => $pelanggan->id, ...$atribut]);
}

test('menampilkan tiket terbuka Pemasangan dan Gangguan dengan nama disamarkan dan PIC', function () {
    $pic = User::factory()->create(['name' => 'Teknisi Joko']);
    $dikerjakan = tiketPapan(['jenis' => JenisTicket::Gangguan, 'status' => StatusTicket::Diproses, 'pic_id' => $pic->id]);
    $menunggu = tiketPapan(['jenis' => JenisTicket::Pemasangan], 'Siti Aminah');
    $selesai = tiketPapan(['status' => StatusTicket::Selesai]);
    $pencabutan = tiketPapan(['jenis' => JenisTicket::Pencabutan]);
    $pelangganTerhapus = tiketPapan();
    $pelangganTerhapus->pelanggan->delete();

    $this->get('/papan-antrean')
        ->assertOk()
        ->assertSeeInOrder(['Sedang Dikerjakan', $dikerjakan->nomor_ticket, 'Teknisi Joko', 'Menunggu Teknisi', $menunggu->nomor_ticket])
        ->assertSee('Bu** Sa*****')
        ->assertSee('Si** Am****')
        ->assertDontSee('Budi Santoso')
        ->assertDontSee($selesai->nomor_ticket)
        ->assertDontSee($pencabutan->nomor_ticket)
        ->assertDontSee($pelangganTerhapus->nomor_ticket);
});
