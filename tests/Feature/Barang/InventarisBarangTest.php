<?php

use App\Actions\Barang\CatatBarangKeluarAction;
use App\Actions\Barang\CatatBarangMasukAction;
use App\Enums\Barang\StatusUnitBarang;
use App\Enums\Barang\TipeMutasiBarang;
use App\Enums\UserStatus;
use App\Exports\DataBarangExport;
use App\Livewire\Barang\Index;
use App\Livewire\Barang\Keluar;
use App\Livewire\Barang\Masuk;
use App\Livewire\Barang\Pengaturan;
use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use App\Models\KondisiBarang;
use App\Models\MutasiBarang;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\UnitBarang;
use App\Models\User;
use Database\Seeders\DevUsersSeeder;
use Database\Seeders\InventarisSeeder;
use Database\Seeders\PengaturanPrefixRegistrasiSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, PengaturanPrefixRegistrasiSeeder::class]);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');

    // Kategori MDM & kondisi NEW/PGT dibuat oleh migrasi inventaris.
    $this->mdm = KategoriBarang::where('kode', 'MDM')->firstOrFail();
    $this->baru = KondisiBarang::where('kode', 'NEW')->firstOrFail();
    $this->pgt = KondisiBarang::where('kode', 'PGT')->firstOrFail();
    $this->bf = PengaturanPrefixRegistrasi::where('kode', 'BF')->firstOrFail();

    $this->modem = JenisBarang::factory()->dilacak()->create(['kategori_barang_id' => $this->mdm->id, 'kode' => 'MDM-F609', 'nama' => 'Modem ZTE F609']);
    $this->kabel = JenisBarang::factory()->create(['kode' => 'KBL-DROP', 'nama' => 'Kabel Drop', 'satuan' => 'meter']);

    $this->masuk = app(CatatBarangMasukAction::class);
    $this->keluar = app(CatatBarangKeluarAction::class);
});

test('barang masuk jenis dilacak meng-generate unit berkode urut per prefix dan tidak memakai ulang nomor', function () {
    $m1 = $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 2, $this->admin, kondisi: $this->baru, brand: $this->bf);

    expect($m1->units->pluck('kode')->all())->toBe(['MDM-NEW-BF-1', 'MDM-NEW-BF-2'])
        ->and($m1->jumlah)->toBe(2);

    UnitBarang::where('kode', 'MDM-NEW-BF-2')->delete();

    $m2 = $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 1, $this->admin, kondisi: $this->baru, brand: $this->bf);
    $m3 = $this->masuk->execute($this->modem, TipeMutasiBarang::SaldoAwal, Carbon::today(), 1, $this->admin, kondisi: $this->pgt);

    expect($m2->units->first()->kode)->toBe('MDM-NEW-BF-3')
        ->and($m3->units->first()->kode)->toBe('MDM-PGT-1');
});

test('barang keluar tidak boleh membuat stok negatif dan pemakaian wajib teknisi', function () {
    $this->masuk->execute($this->kabel, TipeMutasiBarang::SaldoAwal, Carbon::today(), 100, $this->admin);

    expect(fn () => $this->keluar->execute($this->kabel, TipeMutasiBarang::Pemakaian, Carbon::today(), 101, $this->admin, teknisi: $this->teknisi))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->keluar->execute($this->kabel, TipeMutasiBarang::Pemakaian, Carbon::today(), 10, $this->admin))
        ->toThrow(ValidationException::class);

    $this->keluar->execute($this->kabel, TipeMutasiBarang::Pemakaian, Carbon::today(), 40, $this->admin, teknisi: $this->teknisi);
    $this->keluar->execute($this->kabel, TipeMutasiBarang::Rusak, Carbon::today(), 5, $this->admin);

    expect($this->kabel->stok())->toBe(55);
});

test('unit keluar jadi Terpasang, dikembalikan tetap berkode sama dengan kondisi PGT, lalu bisa keluar lagi', function () {
    $unit = $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 1, $this->admin, kondisi: $this->baru, brand: $this->bf)->units->first();

    $this->keluar->execute($this->modem, TipeMutasiBarang::Pemakaian, Carbon::today(), 0, $this->admin, teknisi: $this->teknisi, unitIds: [$unit->id]);
    expect($unit->fresh()->status)->toBe(StatusUnitBarang::Terpasang)
        ->and($this->modem->stok())->toBe(0);

    // Unit terpasang tidak bisa dikeluarkan dua kali.
    expect(fn () => $this->keluar->execute($this->modem, TipeMutasiBarang::Pemakaian, Carbon::today(), 0, $this->admin, teknisi: $this->teknisi, unitIds: [$unit->id]))
        ->toThrow(ValidationException::class);

    $this->masuk->execute($this->modem, TipeMutasiBarang::Pengembalian, Carbon::today(), 0, $this->admin, kondisi: $this->pgt, unitIds: [$unit->id]);
    $unit->refresh();

    expect($unit->kode)->toBe('MDM-NEW-BF-1')
        ->and($unit->status)->toBe(StatusUnitBarang::Dikembalikan)
        ->and($unit->kondisi_barang_id)->toBe($this->pgt->id)
        ->and($this->modem->stok())->toBe(1);

    $this->keluar->execute($this->modem, TipeMutasiBarang::Rusak, Carbon::today(), 0, $this->admin, unitIds: [$unit->id]);
    expect($unit->fresh()->status)->toBe(StatusUnitBarang::Rusak);
});

test('stok periode menghitung stok awal dari bulan sebelumnya, masuk, keluar, dan stok akhir', function () {
    $this->masuk->execute($this->kabel, TipeMutasiBarang::SaldoAwal, Carbon::create(2026, 8, 5), 100, $this->admin);
    $this->keluar->execute($this->kabel, TipeMutasiBarang::Pemakaian, Carbon::create(2026, 8, 20), 30, $this->admin, teknisi: $this->teknisi);
    $this->masuk->execute($this->kabel, TipeMutasiBarang::Pembelian, Carbon::create(2026, 9, 2), 50, $this->admin);
    $this->keluar->execute($this->kabel, TipeMutasiBarang::Pemakaian, Carbon::create(2026, 9, 10), 25, $this->admin, teknisi: $this->teknisi);

    $baris = JenisBarang::stokPeriode(Carbon::create(2026, 9, 1))->whereKey($this->kabel->id)->firstOrFail();

    expect([$baris->stok_awal, $baris->jumlah_masuk, $baris->jumlah_keluar, $baris->stok_akhir])->toBe([70, 50, 25, 95]);

    $export = new DataBarangExport(bulan: Carbon::create(2026, 9, 1), search: 'Drop');
    expect($export->map($export->query()->firstOrFail()))->toBe(['KBL-DROP', 'Kabel Drop', $this->kabel->kategori->nama, 'meter', 70, 50, 25, 95]);
});

test('data barang difilter per stok akhir maksimum dan diurutkan per stok', function () {
    $this->masuk->execute($this->kabel, TipeMutasiBarang::SaldoAwal, Carbon::today(), 100, $this->admin);
    $konektor = JenisBarang::factory()->create(['kode' => 'KON-SC', 'nama' => 'Konektor SC']);
    $this->masuk->execute($konektor, TipeMutasiBarang::SaldoAwal, Carbon::today(), 3, $this->admin);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('stokMaks', 5)
        ->assertSee('Konektor SC')
        ->assertDontSee('Kabel Drop')
        ->set('stokMaks', null)
        ->set('urut', 'stok_desc')
        ->assertSeeInOrder(['Kabel Drop', 'Konektor SC']);
});

test('form barang keluar lewat pindai kode unit, dan teknisi tidak boleh mencatat mutasi', function () {
    $unit = $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 1, $this->admin, kondisi: $this->baru, brand: $this->bf)->units->first();

    Livewire::actingAs($this->admin)
        ->test(Keluar::class)
        ->call('openCreateModal')
        ->set('jenisId', $this->modem->id)
        ->set('scanKode', 'mdm-new-bf-1')
        ->call('tambahUnit')
        ->assertSet('unitIds', [$unit->id])
        ->set('teknisiId', $this->teknisi->id)
        ->set('keterangan', 'Pemasangan pelanggan baru')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($unit->fresh()->status)->toBe(StatusUnitBarang::Terpasang);

    Livewire::actingAs($this->teknisi)->test(Masuk::class)->assertOk()->call('openCreateModal')->assertForbidden();
    Livewire::actingAs($this->teknisi)->test(Keluar::class)->call('openCreateModal')->assertForbidden();
});

test('label barcode PDF tercetak untuk unit hasil barang masuk', function () {
    $mutasi = $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 2, $this->admin, kondisi: $this->baru, brand: $this->bf);

    $this->actingAs($this->admin)
        ->get(route('barang.label', ['mutasi' => $mutasi->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($this->admin)->get(route('barang.label'))->assertNotFound();
});

test('semua halaman inventaris dapat dibuka oleh admin', function () {
    $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 1, $this->admin, kondisi: $this->baru, brand: $this->bf);
    $this->masuk->execute($this->kabel, TipeMutasiBarang::SaldoAwal, Carbon::today(), 10, $this->admin);

    foreach (['barang.index', 'barang.masuk', 'barang.keluar', 'barang.unit', 'barang.pengaturan'] as $route) {
        $this->actingAs($this->admin)->get(route($route))->assertOk();
    }

    $this->actingAs($this->teknisi)->get(route('barang.pengaturan'))->assertForbidden();
});

test('kategori dan kondisi hanya bisa dihapus bila tidak dipakai', function () {
    $kosong = KategoriBarang::create(['kode' => 'ONT', 'nama' => 'ONT']);
    $kondisiKosong = KondisiBarang::create(['kode' => 'RFB', 'nama' => 'Refurbished']);
    $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 1, $this->admin, kondisi: $this->baru);

    Livewire::actingAs($this->admin)
        ->test(Pengaturan::class)
        ->call('hapus', 'kategori', $this->mdm->id)
        ->call('hapus', 'kondisi', $this->baru->id)
        ->call('hapus', 'kategori', $kosong->id)
        ->call('hapus', 'kondisi', $kondisiKosong->id);

    expect(KategoriBarang::find($this->mdm->id))->not->toBeNull()
        ->and(KondisiBarang::find($this->baru->id))->not->toBeNull()
        ->and(KategoriBarang::find($kosong->id))->toBeNull()
        ->and(KondisiBarang::find($kondisiKosong->id))->toBeNull();

    Livewire::actingAs($this->teknisi)->test(Pengaturan::class)->assertForbidden();
});

test('InventarisSeeder membuat data contoh yang konsisten dan aman dijalankan ulang', function () {
    $this->seed(DevUsersSeeder::class);

    $this->seed(InventarisSeeder::class);

    $mutasiAwal = MutasiBarang::count();
    $router = JenisBarang::where('kode', 'MDM-F670')->firstOrFail();

    expect(JenisBarang::count())->toBeGreaterThanOrEqual(10)
        // 5 saldo awal baru + 2 bekas + 10 pembelian - 3 keluar + 1 dikembalikan.
        ->and($router->stok())->toBe(15)
        ->and(UnitBarang::where('kode', 'MDM-NEW-BF-1')->exists())->toBeTrue()
        ->and(UnitBarang::where('kode', 'like', 'MDM-PGT-%')->count())->toBe(2)
        ->and(UnitBarang::where('kode', 'MDM-NEW-BF-1')->value('status'))->toBe(StatusUnitBarang::Dikembalikan)
        ->and(JenisBarang::all()->every(fn (JenisBarang $j) => $j->stok() >= 0))->toBeTrue()
        ->and(MutasiBarang::where('tipe', TipeMutasiBarang::Rusak)->whereNull('teknisi_id')->count())->toBe(1);

    $this->seed(InventarisSeeder::class);
    expect(MutasiBarang::count())->toBe($mutasiAwal);
});
