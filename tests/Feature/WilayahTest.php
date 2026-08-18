<?php

use App\Enums\UserStatus;
use App\Livewire\Wilayah\Kecamatan\Create as KecamatanCreate;
use App\Livewire\Wilayah\Kecamatan\Edit as KecamatanEdit;
use App\Livewire\Wilayah\Kecamatan\Index as KecamatanIndex;
use App\Livewire\Wilayah\Kelurahan\Create as KelurahanCreate;
use App\Livewire\Wilayah\Kelurahan\Index as KelurahanIndex;
use App\Livewire\Wilayah\Kota\Create as KotaCreate;
use App\Livewire\Wilayah\Kota\Edit as KotaEdit;
use App\Livewire\Wilayah\Kota\Index as KotaIndex;
use App\Livewire\Wilayah\Perumahan\Create as PerumahanCreate;
use App\Livewire\Wilayah\Perumahan\Edit as PerumahanEdit;
use App\Livewire\Wilayah\Perumahan\Index as PerumahanIndex;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kota;
use App\Models\Pelanggan;
use App\Models\Perumahan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');
});

test('super admin can view kota index', function () {
    $kota = Kota::factory()->create(['nama_kota' => 'Kota Surabaya']);

    Livewire::actingAs($this->superAdmin)
        ->test(KotaIndex::class)
        ->assertStatus(200)
        ->assertSee('Kota Surabaya');
});

test('super admin can create kota', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(KotaCreate::class)
        ->set('nama_kota', 'Kota Bandung')
        ->set('keterangan', 'Ibu kota Provinsi Jawa Barat')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wilayah.kota.index'));

    expect(Kota::where('nama_kota', 'Kota Bandung')->exists())->toBeTrue();
});

test('validation fails for duplicate kota name', function () {
    Kota::factory()->create(['nama_kota' => 'Kota Bandung']);

    Livewire::actingAs($this->superAdmin)
        ->test(KotaCreate::class)
        ->set('nama_kota', 'Kota Bandung')
        ->call('save')
        ->assertHasErrors(['nama_kota']);
});

test('super admin can edit kota', function () {
    $kota = Kota::factory()->create(['nama_kota' => 'Kota Lama']);

    Livewire::actingAs($this->superAdmin)
        ->test(KotaEdit::class, ['kota' => $kota])
        ->set('nama_kota', 'Kota Baru')
        ->set('keterangan', 'Keterangan diperbarui')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wilayah.kota.index'));

    expect($kota->fresh()->nama_kota)->toBe('Kota Baru')
        ->and($kota->fresh()->keterangan)->toBe('Keterangan diperbarui');
});

test('guarded deletion prevents deleting kota with kecamatans', function () {
    $kota = Kota::factory()->create(['nama_kota' => 'Kota Bandung']);
    Kecamatan::factory()->create(['kota_id' => $kota->id, 'nama_kecamatan' => 'Buahbatu']);

    Livewire::actingAs($this->superAdmin)
        ->test(KotaIndex::class)
        ->call('confirmDelete', $kota->id)
        ->call('deleteKota');

    expect(Kota::where('id', $kota->id)->exists())->toBeTrue();
});

test('kota without kecamatans can be deleted', function () {
    $kota = Kota::factory()->create(['nama_kota' => 'Kota Kosong']);

    Livewire::actingAs($this->superAdmin)
        ->test(KotaIndex::class)
        ->call('confirmDelete', $kota->id)
        ->call('deleteKota');

    expect(Kota::where('id', $kota->id)->exists())->toBeFalse();
});

test('super admin can create kecamatan under a kota', function () {
    $kota = Kota::factory()->create(['nama_kota' => 'Kota Bandung']);

    Livewire::actingAs($this->superAdmin)
        ->test(KecamatanCreate::class)
        ->set('kota_id', $kota->id)
        ->set('nama_kecamatan', 'Lengkong')
        ->set('keterangan', 'Bandung Pusat')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wilayah.kecamatan.index'));

    expect(Kecamatan::where('nama_kecamatan', 'Lengkong')->where('kota_id', $kota->id)->exists())->toBeTrue();
});

test('validation prevents duplicate kecamatan in same kota but allows in different kota', function () {
    $kota1 = Kota::factory()->create(['nama_kota' => 'Kota 1']);
    $kota2 = Kota::factory()->create(['nama_kota' => 'Kota 2']);

    Kecamatan::factory()->create(['kota_id' => $kota1->id, 'nama_kecamatan' => 'Sukasari']);

    // Same kota -> fail
    Livewire::actingAs($this->superAdmin)
        ->test(KecamatanCreate::class)
        ->set('kota_id', $kota1->id)
        ->set('nama_kecamatan', 'Sukasari')
        ->call('save')
        ->assertHasErrors(['nama_kecamatan']);

    // Different kota -> pass
    Livewire::actingAs($this->superAdmin)
        ->test(KecamatanCreate::class)
        ->set('kota_id', $kota2->id)
        ->set('nama_kecamatan', 'Sukasari')
        ->call('save')
        ->assertHasNoErrors();
});

test('super admin can edit kecamatan', function () {
    $kecamatan = Kecamatan::factory()->create(['nama_kecamatan' => 'Kecamatan Awal']);

    Livewire::actingAs($this->superAdmin)
        ->test(KecamatanEdit::class, ['kecamatan' => $kecamatan])
        ->set('nama_kecamatan', 'Kecamatan Revisi')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wilayah.kecamatan.index'));

    expect($kecamatan->fresh()->nama_kecamatan)->toBe('Kecamatan Revisi');
});

test('guarded deletion prevents deleting kecamatan with kelurahans', function () {
    $kecamatan = Kecamatan::factory()->create();
    Kelurahan::factory()->create(['kecamatan_id' => $kecamatan->id]);

    Livewire::actingAs($this->superAdmin)
        ->test(KecamatanIndex::class)
        ->call('confirmDelete', $kecamatan->id)
        ->call('deleteKecamatan');

    expect(Kecamatan::where('id', $kecamatan->id)->exists())->toBeTrue();
});

test('super admin can create kelurahan with cascading select', function () {
    $kota = Kota::factory()->create();
    $kecamatan = Kecamatan::factory()->create(['kota_id' => $kota->id]);

    Livewire::actingAs($this->superAdmin)
        ->test(KelurahanCreate::class)
        ->set('kota_id', $kota->id)
        ->set('kecamatan_id', $kecamatan->id)
        ->set('nama_kelurahan', 'Margasari')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wilayah.kelurahan.index'));

    expect(Kelurahan::where('nama_kelurahan', 'Margasari')->where('kecamatan_id', $kecamatan->id)->exists())->toBeTrue();
});

test('changing kota in kelurahan create resets kecamatan selection', function () {
    $kota1 = Kota::factory()->create();
    $kota2 = Kota::factory()->create();
    $kecamatan = Kecamatan::factory()->create(['kota_id' => $kota1->id]);

    Livewire::actingAs($this->superAdmin)
        ->test(KelurahanCreate::class)
        ->set('kota_id', $kota1->id)
        ->set('kecamatan_id', $kecamatan->id)
        ->set('kota_id', $kota2->id)
        ->assertSet('kecamatan_id', null);
});

test('guarded deletion prevents deleting kelurahan with perumahans', function () {
    $kelurahan = Kelurahan::factory()->create();
    Perumahan::factory()->create(['kelurahan_id' => $kelurahan->id]);

    Livewire::actingAs($this->superAdmin)
        ->test(KelurahanIndex::class)
        ->call('confirmDelete', $kelurahan->id)
        ->call('deleteKelurahan');

    expect(Kelurahan::where('id', $kelurahan->id)->exists())->toBeTrue();
});

test('super admin can create perumahan with coordinates and uppercase singkatan', function () {
    $kota = Kota::factory()->create();
    $kecamatan = Kecamatan::factory()->create(['kota_id' => $kota->id]);
    $kelurahan = Kelurahan::factory()->create(['kecamatan_id' => $kecamatan->id]);

    Livewire::actingAs($this->superAdmin)
        ->test(PerumahanCreate::class)
        ->set('kota_id', $kota->id)
        ->set('kecamatan_id', $kecamatan->id)
        ->set('kelurahan_id', $kelurahan->id)
        ->set('nama_perumahan', 'Griya Bandung Indah')
        ->set('singkatan', 'gbi')
        ->set('lat', -6.9663450)
        ->set('lng', 107.6698120)
        ->set('keterangan', 'Cluster Blok A-H')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wilayah.perumahan.index'));

    $perumahan = Perumahan::where('nama_perumahan', 'Griya Bandung Indah')->first();
    expect($perumahan)->not->toBeNull()
        ->and($perumahan->singkatan)->toBe('GBI')
        ->and((float) $perumahan->latitude)->toBe(-6.9663450)
        ->and((float) $perumahan->longitude)->toBe(107.6698120);
});

test('super admin can edit perumahan and update coordinates', function () {
    $perumahan = Perumahan::factory()->create([
        'nama_perumahan' => 'Cluster Lama',
        'singkatan' => 'OLD',
        'latitude' => -6.9000000,
        'longitude' => 107.6000000,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(PerumahanEdit::class, ['perumahan' => $perumahan])
        ->set('nama_perumahan', 'Cluster Baru')
        ->set('singkatan', 'new')
        ->set('lat', -6.9500000)
        ->set('lng', 107.6500000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wilayah.perumahan.index'));

    $fresh = $perumahan->fresh();
    expect($fresh->nama_perumahan)->toBe('Cluster Baru')
        ->and($fresh->singkatan)->toBe('NEW')
        ->and((float) $fresh->latitude)->toBe(-6.9500000)
        ->and((float) $fresh->longitude)->toBe(107.6500000);
});

test('guarded deletion prevents deleting perumahan with registered pelanggan or odp', function () {
    $perumahan = Perumahan::factory()->create();

    // Attach pelanggan
    Pelanggan::factory()->create([
        'perumahan_id' => $perumahan->id,
        'dibuat_oleh' => $this->superAdmin->id,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(PerumahanIndex::class)
        ->call('confirmDelete', $perumahan->id)
        ->call('deletePerumahan');

    expect(Perumahan::where('id', $perumahan->id)->exists())->toBeTrue();
});

test('perumahan index can trigger interactive map modal', function () {
    $perumahan = Perumahan::factory()->create([
        'latitude' => -6.9554200,
        'longitude' => 107.6256400,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(PerumahanIndex::class)
        ->call('showMap', $perumahan->id)
        ->assertSet('viewingMapId', $perumahan->id)
        ->call('closeMap')
        ->assertSet('viewingMapId', null);
});

test('wilayah seeder executes and populates hierarchy with coordinates', function () {
    $this->seed(WilayahSeeder::class);

    expect(Kota::where('nama_kota', 'Kota Bandung')->exists())->toBeTrue()
        ->and(Kecamatan::where('nama_kecamatan', 'Buahbatu')->exists())->toBeTrue()
        ->and(Kelurahan::where('nama_kelurahan', 'Margasari')->exists())->toBeTrue();

    $gbi = Perumahan::where('nama_perumahan', 'Griya Bandung Indah')->first();
    expect($gbi)->not->toBeNull()
        ->and($gbi->singkatan)->toBe('GBI')
        ->and((float) $gbi->latitude)->toBe(-6.9663450)
        ->and((float) $gbi->longitude)->toBe(107.6698120);
});
