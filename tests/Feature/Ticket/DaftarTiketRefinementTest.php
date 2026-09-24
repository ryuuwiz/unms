<?php

use App\Enums\Ticket\PrioritasTicket;
use App\Enums\Ticket\StatusTicket;
use App\Enums\UserStatus;
use App\Livewire\Ticket\Index;
use App\Models\Pelanggan;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(Carbon::create(2026, 9, 24, 10, 0, 0));

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');
});

function buatTiket(string $kata, array $atribut = []): Ticket
{
    return Ticket::factory()->create($atribut + [
        'deskripsi' => $kata,
        'pelanggan_id' => Pelanggan::factory()->create(['nama_depan' => $kata, 'no_hp' => '0812'.random_int(10000000, 99999999)])->id,
    ]);
}

test('urutan: overdue dulu, lalu prioritas, lalu SLA terdekat, dan yang selesai terbaru dulu', function () {
    $sedangBaru = buatTiket('SedangBaru', ['prioritas' => PrioritasTicket::Sedang, 'sla_target_selesai' => now()->addDays(2)]);
    $daruratAman = buatTiket('DaruratAman', ['prioritas' => PrioritasTicket::Darurat, 'sla_target_selesai' => now()->addHours(3)]);
    $rendahOverdue = buatTiket('RendahOverdue', ['prioritas' => PrioritasTicket::Rendah, 'sla_target_selesai' => now()->subHours(2)]);
    $tinggiDekat = buatTiket('TinggiDekat', ['prioritas' => PrioritasTicket::Tinggi, 'sla_target_selesai' => now()->addHours(5)]);
    $tinggiJauh = buatTiket('TinggiJauh', ['prioritas' => PrioritasTicket::Tinggi, 'sla_target_selesai' => now()->addHours(20)]);
    $selesaiLama = buatTiket('SelesaiLama', ['prioritas' => PrioritasTicket::Darurat, 'status' => StatusTicket::Selesai]);
    $selesaiBaru = buatTiket('SelesaiBaru', ['prioritas' => PrioritasTicket::Rendah, 'status' => StatusTicket::Batal]);

    $urutan = Ticket::query()->urutkanPrioritas()->pluck('id')->all();

    expect($urutan)->toBe([
        $rendahOverdue->id, $daruratAman->id, $tinggiDekat->id, $tinggiJauh->id, $sedangBaru->id,
        $selesaiBaru->id, $selesaiLama->id,
    ]);

    // Opsi "Terbaru" mengembalikan urutan id menurun.
    $komponen = Livewire::actingAs($this->admin)->test(Index::class)->set('urut', 'terbaru');
    expect($komponen->viewData('tickets')->pluck('id')->all())->toBe(collect($urutan)->sortDesc()->values()->all());
});

test('tab awal: Tiket Saya bagi PIC tiket terbuka, Semua Tiket bagi lainnya, dan URL menang', function () {
    buatTiket('Milik Teknisi', ['pic_id' => $this->teknisi->id]);

    Livewire::actingAs($this->teknisi)->test(Index::class)->assertSet('tab', 'saya');
    Livewire::actingAs($this->admin)->test(Index::class)->assertSet('tab', 'semua');
    Livewire::withQueryParams(['tab' => 'semua'])->actingAs($this->teknisi)->test(Index::class)->assertSet('tab', 'semua');

    // Tiket PIC yang sudah selesai tidak membuat tab awal berpindah.
    Ticket::query()->update(['status' => StatusTicket::Selesai]);
    Livewire::actingAs($this->teknisi)->test(Index::class)->assertSet('tab', 'semua');
});

test('filter, jumlah filter aktif, dan Reset filter', function () {
    buatTiket('Alpha', ['prioritas' => PrioritasTicket::Darurat]);
    buatTiket('Beta', ['prioritas' => PrioritasTicket::Rendah]);

    $komponen = Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('prioritas', PrioritasTicket::Darurat->value)
        ->assertSee('Alpha')
        ->assertDontSee('Beta')
        ->assertViewHas('jumlahFilterAktif', 1)
        ->assertSee('Reset filter');

    $komponen->call('resetFilter')
        ->assertSet('prioritas', '')
        ->assertViewHas('jumlahFilterAktif', 0)
        ->assertSee('Alpha')
        ->assertSee('Beta');

    $komponen->set('search', 'tidak-ada-yang-cocok')->assertSee('Tidak ada tiket yang cocok dengan pencarian/filter ini.');
});

test('kartu dan tabel dirender; tel: selalu ada dan tautan Maps hanya bila pelanggan punya koordinat', function () {
    $denganKoordinat = buatTiket('DenganPeta');
    $denganKoordinat->pelanggan->update(['no_hp' => '0812-3456-7890', 'latitude' => -6.9, 'longitude' => 107.6]);
    $tanpaKoordinat = buatTiket('TanpaPeta');
    $tanpaKoordinat->pelanggan->update(['no_hp' => '0813 0000 1111', 'latitude' => null, 'longitude' => null]);

    $html = Livewire::actingAs($this->admin)->test(Index::class)->html();

    expect($html)
        ->toContain('id="panel-filter-tiket"')
        ->toContain('role="tablist"')
        ->toContain('kartu-'.$denganKoordinat->id)
        ->toContain('baris-'.$denganKoordinat->id)
        ->toContain('href="tel:081234567890"')
        ->toContain('href="tel:081300001111"')
        ->toContain('destination=-6.9,107.6')
        ->and(substr_count($html, 'maps/dir/?api=1'))->toBe(2); // kartu + baris tabel milik satu pelanggan berkoordinat
});
