<?php

use App\Livewire\Billing\SiklusTagihan\Index;
use App\Models\PengaturanSiklusTagihan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('admin melihat nilai bawaan dan dapat menyimpan Siklus Tagihan', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSet('hari_jatuh_tempo', 10)
        ->assertSet('hari_terbit_invoice', 24)
        ->set('hari_jatuh_tempo', 15)
        ->set('hari_terbit_invoice', 1)
        ->call('save')
        ->assertHasNoErrors();

    $pengaturan = PengaturanSiklusTagihan::ambil();
    expect($pengaturan->hari_jatuh_tempo)->toBe(15)
        ->and($pengaturan->hari_terbit_invoice)->toBe(1);
});

test('menolak hari di luar 1-28 dan hari terbit yang sama dengan hari jatuh tempo', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('hari_jatuh_tempo', 31)
        ->set('hari_terbit_invoice', 0)
        ->call('save')
        ->assertHasErrors(['hari_jatuh_tempo', 'hari_terbit_invoice']);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('hari_jatuh_tempo', 12)
        ->set('hari_terbit_invoice', 12)
        ->call('save')
        ->assertHasErrors(['hari_terbit_invoice']);

    expect(PengaturanSiklusTagihan::ambil()->hari_jatuh_tempo)->toBe(10);
});

test('peran tanpa izin siklus_tagihan.ubah mendapat 403', function () {
    $noc = User::factory()->create();
    $noc->assignRole('noc');

    $this->actingAs($noc)
        ->get(route('billing.siklus-tagihan.index'))
        ->assertForbidden();
});

test('tanggal terbit adalah Hari Terbit terakhir sebelum jatuh tempo dan lead time mengikutinya', function () {
    $siklus = new PengaturanSiklusTagihan(['hari_jatuh_tempo' => 10, 'hari_terbit_invoice' => 24]);

    expect($siklus->tanggalTerbit(Carbon::parse('2026-10-10'))->toDateString())->toBe('2026-09-24');

    $siklus->hari_terbit_invoice = 5;
    expect($siklus->tanggalTerbit(Carbon::parse('2026-10-10'))->toDateString())->toBe('2026-10-05');

    $this->travelTo(now()->setDate(2026, 9, 19));
    $siklus->hari_terbit_invoice = 24;
    expect($siklus->leadDays())->toBe(16);
});
