<?php

use App\Enums\StatusLayanan;
use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Index;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->sales1 = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales1->assignRole('sales');

    $this->sales2 = User::factory()->create(['status' => UserStatus::Active]);
    $this->sales2->assignRole('sales');
});

test('can list and paginate pelanggans', function () {
    Pelanggan::factory()->count(10)->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->assertOk()
        ->assertViewHas('pelanggans', fn ($p) => $p->count() === 10);
});

test('can search pelanggans by name or no_hp', function () {
    Pelanggan::factory()->create(['nama_depan' => 'Rian', 'nama_belakang' => 'Hidayat', 'no_hp' => '628123456789']);
    Pelanggan::factory()->create(['nama_depan' => 'Siti', 'nama_belakang' => 'Aminah', 'no_hp' => '628987654321']);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('search', 'Rian')
        ->assertSee('Rian')
        ->assertDontSee('Siti');
});

test('sales can filter to view only their own pelanggans', function () {
    Pelanggan::factory()->create(['nama_depan' => 'Milik Sales 1', 'dibuat_oleh' => $this->sales1->id]);
    Pelanggan::factory()->create(['nama_depan' => 'Milik Sales 2', 'dibuat_oleh' => $this->sales2->id]);

    Livewire::actingAs($this->sales1)
        ->test(Index::class)
        ->set('filterScope', 'my')
        ->assertSee('Milik Sales 1')
        ->assertDontSee('Milik Sales 2');
});

test('hapus pelanggan menghapus record yang diklik setelah mengetik HAPUS', function () {
    $lain = Pelanggan::factory()->create();
    $target = Pelanggan::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $target->id)
        ->assertSet('showHapusModal', true)
        ->set('showHapusModal', true) // flux:modal menulis true ke wire:model saat terbuka
        ->set('konfirmasiHapus', 'HAPUS')
        ->call('deleteCustomer')
        ->assertHasNoErrors()
        ->assertSet('showHapusModal', false);

    expect(Pelanggan::find($target->id))->toBeNull()
        ->and(Pelanggan::find($lain->id))->not->toBeNull();
});

test('hapus pelanggan ditolak tanpa ketik HAPUS', function () {
    $target = Pelanggan::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $target->id)
        ->set('konfirmasiHapus', 'hapus')
        ->call('deleteCustomer')
        ->assertHasErrors('konfirmasiHapus');

    expect(Pelanggan::find($target->id))->not->toBeNull();
});

test('hapus pelanggan ditolak selama masih punya registrasi billing yang belum Berhenti', function () {
    $target = Pelanggan::factory()->create();
    LayananPelanggan::factory()->create(['pelanggan_id' => $target->id, 'status' => StatusLayanan::Proses]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $target->id)
        ->assertSet('showHapusModal', false);

    expect(Pelanggan::find($target->id))->not->toBeNull();
});

test('hapus pelanggan diizinkan bila semua registrasi billing sudah Berhenti', function () {
    $target = Pelanggan::factory()->create();
    LayananPelanggan::factory()->create(['pelanggan_id' => $target->id, 'status' => StatusLayanan::Berhenti]);

    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->call('confirmDelete', $target->id)
        ->set('konfirmasiHapus', 'HAPUS')
        ->call('deleteCustomer')
        ->assertHasNoErrors();

    expect(Pelanggan::find($target->id))->toBeNull();
});

test('admin tidak bisa menghapus pelanggan', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', Pelanggan::factory()->create()->id)
        ->assertForbidden();
});

test('deletingCustomerId pelanggan tidak bisa diubah dari client', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Index::class)
        ->set('deletingCustomerId', 1);
})->throws(CannotUpdateLockedPropertyException::class);
