<?php

use App\Enums\Ticket\DivisiTicket;
use App\Enums\UserStatus;
use App\Livewire\Rab\Index;
use App\Models\RabItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo('2026-09-15');

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');
});

it('menyimpan item dengan divisi enum atau divisi lainnya', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('openCreateModal')
        ->set(['uraian' => 'Kertas A4', 'qty' => 2, 'harga' => 50000, 'divisi' => 'admin'])
        ->call('simpan')
        ->assertHasNoErrors()
        ->call('openCreateModal')
        ->set(['uraian' => 'Konsumsi rapat', 'qty' => 1, 'harga' => 200000, 'divisi' => Index::DIVISI_LAINNYA, 'divisiLainnya' => 'Budi, Andi'])
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertSee('Rp 300.000');

    expect(RabItem::orderBy('id')->get()->map(fn (RabItem $i) => [$i->periode->toDateString(), $i->divisi, $i->divisi_lainnya])->all())
        ->toBe([['2026-09-01', DivisiTicket::Admin, null], ['2026-09-01', null, 'Budi, Andi']]);
});

it('mewajibkan nama saat divisi lainnya dipilih', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->set(['uraian' => 'Tinta', 'qty' => 1, 'harga' => 1000, 'divisi' => Index::DIVISI_LAINNYA, 'divisiLainnya' => ''])
        ->call('simpan')
        ->assertHasErrors(['divisiLainnya' => 'required_if']);
});

it('hanya menampilkan item pada bulan yang difilter', function () {
    RabItem::factory()->create(['periode' => '2026-09-01', 'uraian' => 'Item September']);
    RabItem::factory()->create(['periode' => '2026-10-01', 'uraian' => 'Item Oktober']);

    Livewire::actingAs($this->admin)->withQueryParams(['bulan' => '2026-10'])->test(Index::class)
        ->assertSee('Item Oktober')
        ->assertDontSee('Item September');
});

it('menolak perubahan bulan terkunci tanpa izin buka kunci', function () {
    $item = RabItem::factory()->create(['periode' => '2026-08-01']);

    Livewire::actingAs($this->admin)->test(Index::class)->call('hapus', $item->id)->assertForbidden();

    $this->admin->givePermissionTo('rab.buka_kunci');

    Livewire::actingAs($this->admin)->test(Index::class)->call('hapus', $item->id)->assertOk();
    expect(RabItem::count())->toBe(0);
});

it('menyalin item bulan sebelumnya hanya ke bulan yang masih kosong', function () {
    RabItem::factory()->count(3)->create(['periode' => '2026-09-01']);

    Livewire::actingAs($this->admin)->withQueryParams(['bulan' => '2026-10'])->test(Index::class)
        ->call('salinBulanLalu')
        ->call('salinBulanLalu');

    expect(RabItem::whereDate('periode', '2026-10-01')->count())->toBe(3);
});

it('mewajibkan izin rab.lihat', function () {
    $teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $teknisi->assignRole('teknisi');

    $this->actingAs($teknisi)->get(route('rab.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('rab.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('rab.cetak', ['bulan' => '2026-09']))->assertOk();
});
