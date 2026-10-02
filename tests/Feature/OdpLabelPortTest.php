<?php

use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Enums\UserStatus;
use App\Livewire\Odp\Show;
use App\Models\LayananPelanggan;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->noc = User::factory()->create(['status' => UserStatus::Active]);
    $this->noc->assignRole('noc');

    $this->odp = Odp::factory()->create(['nama_odp' => 'ODP-GRH-02']);
    OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 1]);
    OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 2, 'status' => StatusOdpPort::Rusak]);

    $this->portTerpakai = collect([7, 3])->map(function (int $nomor) {
        $layanan = LayananPelanggan::factory()->create(['status' => StatusLayanan::Aktif]);
        $port = OdpPort::factory()->create([
            'odp_id' => $this->odp->id,
            'nomor_port' => $nomor,
            'status' => StatusOdpPort::Terpakai,
            'layanan_pelanggan_id' => $layanan->id,
        ]);
        $layanan->update(['odp_port_id' => $port->id]);

        return $port;
    });

    $this->labelTercetak = null;
    View::composer('pdf.label-port', function ($view) {
        $this->labelTercetak = $view->getData()['labels'];
    });
});

test('cetak massal Label Port dari ODP hanya memuat port Terpakai, urut nomor port', function () {
    $this->actingAs($this->noc)
        ->get(route('odp.label-port', $this->odp))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(array_column($this->labelTercetak, 'port'))->toBe([3, 7])
        ->and($this->labelTercetak[0]['site_id'])->toBe($this->portTerpakai[1]->layananPelanggan->site_id);
});

test('cetak Label Port satu port dari halaman ODP', function () {
    $this->actingAs($this->noc)
        ->get(route('odp.label-port', ['odp' => $this->odp, 'port' => $this->portTerpakai[0]->id]))
        ->assertOk();

    expect(array_column($this->labelTercetak, 'port'))->toBe([7]);
});

test('Label Port ODP tidak tersedia bila tidak ada port Terpakai yang cocok', function () {
    $odpKosong = Odp::factory()->create();
    $portKosong = OdpPort::factory()->create(['odp_id' => $this->odp->id, 'nomor_port' => 9]);

    $this->actingAs($this->noc)->get(route('odp.label-port', $odpKosong))->assertNotFound();
    $this->actingAs($this->noc)->get(route('odp.label-port', ['odp' => $this->odp, 'port' => $portKosong->id]))->assertNotFound();
});

test('user tanpa izin odp.lihat tidak boleh mencetak Label Port ODP', function () {
    $teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $teknisi->assignRole('teknisi');

    expect($teknisi->can('odp.lihat'))->toBeFalse();
    $this->actingAs($teknisi)->get(route('odp.label-port', $this->odp))->assertForbidden();
});

test('halaman detail ODP menampilkan Peta Port ODP dan tombol cetak Label Port', function () {
    Livewire::actingAs($this->noc)
        ->test(Show::class, ['odp' => $this->odp])
        ->assertSee('2/4 terpakai')
        ->assertSee('Cetak semua port Terpakai')
        ->assertSee(route('odp.label-port', ['odp' => $this->odp, 'port' => $this->portTerpakai[0]->id]), escape: false);
});
