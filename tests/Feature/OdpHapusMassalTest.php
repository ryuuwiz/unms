<?php

use App\Enums\StatusOdpPort;
use App\Enums\UserStatus;
use App\Livewire\Odp\Index;
use App\Models\LayananPelanggan;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');
});

function odpDipakai(string $nama): Odp
{
    $odp = Odp::factory()->create(['nama_odp' => $nama]);
    $port = OdpPort::factory()->create(['odp_id' => $odp->id, 'status' => StatusOdpPort::Kosong]);
    LayananPelanggan::factory()->create(['odp_port_id' => $port->id]);

    return $odp;
}

test('hapus massal terpilih menghapus ODP kosong, melewati ODP yang port-nya dipakai, dan tercatat di audit', function () {
    $kosong = Odp::factory()->create(['nama_odp' => 'ODP-KOSONG']);
    OdpPort::factory()->create(['odp_id' => $kosong->id]);
    $dipakaiLayanan = odpDipakai('ODP-DIPAKAI');
    $terpakai = Odp::factory()->create(['nama_odp' => 'ODP-TERPAKAI']);
    OdpPort::factory()->create(['odp_id' => $terpakai->id, 'status' => StatusOdpPort::Terpakai]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('dipilih', [$kosong->id, $dipakaiLayanan->id, $terpakai->id])
        ->call('bukaHapusMassal')
        ->assertSet('showHapusModal', true)
        ->call('hapusMassal')
        ->assertSet('dipilih', []);

    expect(Odp::find($kosong->id))->toBeNull()
        ->and(OdpPort::where('odp_id', $kosong->id)->exists())->toBeFalse()
        ->and(Odp::find($dipakaiLayanan->id))->not->toBeNull()
        ->and(Odp::find($terpakai->id))->not->toBeNull()
        ->and(LayananPelanggan::whereNotNull('odp_port_id')->count())->toBe(1);

    $log = Activity::where('log_name', 'odp')->latest('id')->firstOrFail();
    expect($log->getProperty('odp'))->toBe([['id' => $kosong->id, 'nama' => 'ODP-KOSONG']]);
});

test('pilih semua hasil pencarian menghapus lintas halaman dan lebih dari 10 wajib ketik HAPUS', function () {
    Odp::factory()->count(20)->sequence(fn ($s) => ['nama_odp' => 'ODP-CARI-'.$s->index])->create();
    Odp::factory()->create(['nama_odp' => 'ODP-LAIN']);

    $komponen = Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('search', 'CARI')
        ->call('pilihSemua')
        ->call('bukaHapusMassal')
        ->call('hapusMassal')
        ->assertHasErrors(['konfirmasiHapus']);

    expect(Odp::count())->toBe(21);

    $komponen->set('konfirmasiHapus', 'HAPUS')->call('hapusMassal')->assertHasNoErrors();

    expect(Odp::count())->toBe(1)
        ->and(Odp::first()->nama_odp)->toBe('ODP-LAIN');
});

test('hapus satuan juga menolak ODP yang port-nya dipakai layanan', function () {
    $odp = odpDipakai('ODP-SATU');

    Livewire::actingAs($this->admin)->test(Index::class)->call('deleteOdp', $odp->id);

    expect(Odp::find($odp->id))->not->toBeNull();
});

test('pengguna tanpa odp.hapus tidak dapat hapus massal', function () {
    $teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $teknisi->assignRole('teknisi');
    $teknisi->givePermissionTo('odp.lihat');
    $odp = Odp::factory()->create();

    Livewire::actingAs($teknisi)->test(Index::class)->set('dipilih', [$odp->id])->call('hapusMassal')->assertForbidden();

    expect(Odp::find($odp->id))->not->toBeNull();
});
