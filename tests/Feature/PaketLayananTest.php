<?php

use App\Enums\StatusPaket;
use App\Enums\UserStatus;
use App\Livewire\PaketLayanan\Create;
use App\Livewire\PaketLayanan\Edit;
use App\Livewire\PaketLayanan\Index;
use App\Models\PaketLayanan;
use App\Models\ProfilBandwidth;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');

    $this->salesUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->salesUser->assignRole('sales');

    $this->profilBandwidth = ProfilBandwidth::factory()->create([
        'nama_bandwidth' => '20Mbps-Standard',
        'max_limit_tx' => 20,
        'max_limit_rx' => 20,
    ]);
});

test('user with paket_layanan.lihat permission can access paket layanan index', function () {
    Livewire::actingAs($this->superAdmin)->test(Index::class)->assertOk();
    Livewire::actingAs($this->adminUser)->test(Index::class)->assertOk();
    Livewire::actingAs($this->salesUser)->test(Index::class)->assertOk();
});

test('unauthenticated user is redirected to login on paket layanan route', function () {
    $this->get(route('paket-layanan.index'))
        ->assertRedirect(route('login'));
});

test('admin can create paket layanan and it records activity log', function () {
    Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('nama_paket', 'Paket Home 20 Mbps')
        ->set('profil_bandwidth_id', $this->profilBandwidth->id)
        ->set('harga', 250000)
        ->set('masa_aktif_nilai', 1)
        ->set('masa_aktif_satuan', 'bulan')
        ->set('status', 'aktif')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('paket-layanan.index'));

    $paket = PaketLayanan::where('nama_paket', 'Paket Home 20 Mbps')->first();
    expect($paket)->not->toBeNull()
        ->and($paket->profil_bandwidth_id)->toBe($this->profilBandwidth->id)
        ->and((float) $paket->harga)->toBe(250000.0)
        ->and($paket->status)->toBe(StatusPaket::Aktif);

    expect(Activity::where('subject_type', PaketLayanan::class)->where('subject_id', $paket->id)->exists())->toBeTrue();
});

test('validation prevents duplicate paket name', function () {
    PaketLayanan::factory()->create(['nama_paket' => 'Paket Existing 20 Mbps']);

    Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('nama_paket', 'Paket Existing 20 Mbps')
        ->set('profil_bandwidth_id', $this->profilBandwidth->id)
        ->set('harga', 200000)
        ->call('save')
        ->assertHasErrors(['nama_paket' => 'unique']);
});

test('admin can edit existing paket layanan', function () {
    $paket = PaketLayanan::factory()->create([
        'nama_paket' => 'Paket Awal',
        'profil_bandwidth_id' => $this->profilBandwidth->id,
        'harga' => 150000,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Edit::class, ['paketLayanan' => $paket])
        ->set('nama_paket', 'Paket Direvisi')
        ->set('harga', 175000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('paket-layanan.index'));

    expect($paket->fresh()->nama_paket)->toBe('Paket Direvisi')
        ->and((float) $paket->fresh()->harga)->toBe(175000.0);
});

test('can toggle status paket layanan', function () {
    $paket = PaketLayanan::factory()->create([
        'status' => StatusPaket::Aktif,
        'profil_bandwidth_id' => $this->profilBandwidth->id,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->call('toggleStatus', $paket->id);

    expect($paket->fresh()->status)->toBe(StatusPaket::Nonaktif);
});
