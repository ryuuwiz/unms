<?php

use App\Enums\Wa\TipePengingatTagihan;
use App\Livewire\Billing\AturanPengingat\Index;
use App\Models\AturanPengingatTagihan;
use App\Models\User;
use App\Models\WaTemplate;
use Database\Seeders\AturanPengingatTagihanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        WaTemplateSeeder::class,
        AturanPengingatTagihanSeeder::class,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('halaman aturan pengingat tagihan dapat diakses staf berwenang', function () {
    $this->actingAs($this->admin)
        ->get(route('billing.aturan-pengingat.index'))
        ->assertOk()
        ->assertSee('Aturan Pengingat Tagihan');
});

test('staf dapat menambah aturan pengingat baru', function () {
    $template = WaTemplate::first();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('nama_aturan', 'Pengingat Spesial H-7')
        ->set('tipe_pengingat', TipePengingatTagihan::SebelumJatuhTempo->value)
        ->set('hari_offset', 7)
        ->set('jam_eksekusi', '09:00')
        ->set('template_id', $template->id)
        ->set('kirim_ulang_berkala', false)
        ->set('is_aktif', true)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(AturanPengingatTagihan::where('nama_aturan', 'Pengingat Spesial H-7')->exists())->toBeTrue();
});

test('staf dapat toggle status aktif aturan pengingat', function () {
    $aturan = AturanPengingatTagihan::first();
    $initialStatus = $aturan->is_aktif;

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('toggleStatus', $aturan->id);

    expect($aturan->fresh()->is_aktif)->toBe(! $initialStatus);
});

test('staf dapat menghapus aturan pengingat', function () {
    $aturan = AturanPengingatTagihan::factory()->create([
        'template_id' => WaTemplate::first()->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('hapus', $aturan->id);

    expect(AturanPengingatTagihan::find($aturan->id))->toBeNull();
});
