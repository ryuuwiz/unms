<?php

use App\Actions\Barang\CatatBarangKeluarAction;
use App\Actions\Barang\CatatBarangMasukAction;
use App\Enums\Barang\StatusUnitBarang;
use App\Enums\Barang\TipeMutasiBarang;
use App\Enums\UserStatus;
use App\Livewire\Barang\Index;
use App\Livewire\Barang\Keluar;
use App\Livewire\Barang\Masuk;
use App\Livewire\Barang\Unit;
use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use App\Models\KondisiBarang;
use App\Models\MutasiBarang;
use App\Models\UnitBarang;
use App\Models\User;
use Database\Seeders\PengaturanPrefixRegistrasiSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, PengaturanPrefixRegistrasiSeeder::class]);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    [$this->teknisiA, $this->teknisiB] = User::factory()->count(2)->create(['status' => UserStatus::Active])
        ->each(fn (User $u) => $u->assignRole('teknisi'))->all();

    $this->mdm = KategoriBarang::where('kode', 'MDM')->firstOrFail();
    $this->baru = KondisiBarang::where('kode', 'NEW')->firstOrFail();
    $this->pgt = KondisiBarang::where('kode', 'PGT')->firstOrFail();

    $this->modem = JenisBarang::factory()->dilacak()->create(['kategori_barang_id' => $this->mdm->id, 'kode' => 'MDM-F609', 'nama' => 'Modem ZTE F609']);
    $this->kabel = JenisBarang::factory()->create(['kode' => 'KBL-DROP', 'nama' => 'Kabel Drop', 'satuan' => 'meter']);

    $this->masuk = app(CatatBarangMasukAction::class);
    $this->keluar = app(CatatBarangKeluarAction::class);
});

test('hapus mutasi unit hanya dari ujung riwayat, dan efeknya dibalik', function () {
    $pembelian = $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 1, $this->admin, kondisi: $this->baru);
    $unit = $pembelian->units->first();
    $keluar = $this->keluar->execute($this->modem, TipeMutasiBarang::Pemakaian, Carbon::today(), 0, $this->admin, teknisi: [$this->teknisiA], unitIds: [$unit->id]);
    $kembali = $this->masuk->execute($this->modem, TipeMutasiBarang::Pengembalian, Carbon::today(), 0, $this->admin, kondisi: $this->pgt, unitIds: [$unit->id]);

    // Pembelian dan Barang Keluar bukan mutasi terakhir unit ini.
    Livewire::actingAs($this->admin)->test(Masuk::class)->call('hapus', $pembelian->id);
    Livewire::actingAs($this->admin)->test(Keluar::class)->call('hapus', $keluar->id);
    expect(MutasiBarang::count())->toBe(3);

    Livewire::actingAs($this->admin)->test(Masuk::class)->call('hapus', $kembali->id);
    expect($unit->fresh())->status->toBe(StatusUnitBarang::Terpasang)->kondisi_barang_id->toBe($this->baru->id);

    Livewire::actingAs($this->admin)->test(Keluar::class)->call('hapus', $keluar->id);
    expect($unit->fresh()->status)->toBe(StatusUnitBarang::DiGudang);

    Livewire::actingAs($this->admin)->test(Masuk::class)->call('hapus', $pembelian->id);
    expect(MutasiBarang::count())->toBe(0)->and(UnitBarang::count())->toBe(0);

    // Nomor kode unit yang terhapus tidak dipakai ulang.
    expect($this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 1, $this->admin, kondisi: $this->baru)->units->first()->kode)
        ->toBe('MDM-NEW-2');
});

test('hapus barang masuk ditolak bila stok jadi negatif sesudahnya', function () {
    $saldo = $this->masuk->execute($this->kabel, TipeMutasiBarang::SaldoAwal, Carbon::create(2026, 9, 1), 100, $this->admin);
    $beli = $this->masuk->execute($this->kabel, TipeMutasiBarang::Pembelian, Carbon::create(2026, 9, 2), 50, $this->admin);
    $this->keluar->execute($this->kabel, TipeMutasiBarang::Pemakaian, Carbon::create(2026, 9, 3), 120, $this->admin, teknisi: [$this->teknisiA]);

    Livewire::actingAs($this->admin)->test(Masuk::class)->call('hapus', $beli->id);
    Livewire::actingAs($this->admin)->test(Masuk::class)->call('hapus', $saldo->id);
    expect($this->kabel->stok())->toBe(30);

    $this->masuk->execute($this->kabel, TipeMutasiBarang::Pembelian, Carbon::create(2026, 9, 2), 200, $this->admin);
    Livewire::actingAs($this->admin)->test(Masuk::class)->call('hapus', $beli->id);
    expect($this->kabel->stok())->toBe(180);
});

test('teknisi tidak boleh menghapus mutasi', function () {
    $mutasi = $this->masuk->execute($this->kabel, TipeMutasiBarang::Pembelian, Carbon::today(), 10, $this->admin);

    Livewire::actingAs($this->teknisiA)->test(Masuk::class)->call('hapus', $mutasi->id)->assertForbidden();
});

test('form barang masuk tanpa tipe: unit terpasang yang dipindai tercatat pengembalian, selain itu pembelian', function () {
    $unit = $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 1, $this->admin, kondisi: $this->baru)->units->first();
    $this->keluar->execute($this->modem, TipeMutasiBarang::Pemakaian, Carbon::today(), 0, $this->admin, teknisi: [$this->teknisiA], unitIds: [$unit->id]);

    Livewire::actingAs($this->admin)->test(Masuk::class)
        ->call('openCreateModal')
        ->set('jenisId', $this->modem->id)
        ->set('scanKode', $unit->kode)
        ->call('tambahUnit')
        ->set('kondisiId', $this->pgt->id)
        ->call('simpan')
        ->assertHasNoErrors()
        ->call('openCreateModal')
        ->set('jenisId', $this->kabel->id)
        ->set('jumlah', 25)
        ->call('simpan')
        ->assertHasNoErrors();

    expect($unit->fresh()->status)->toBe(StatusUnitBarang::Dikembalikan)
        ->and(MutasiBarang::where('tipe', TipeMutasiBarang::Pengembalian)->count())->toBe(1)
        ->and(MutasiBarang::where('jenis_barang_id', $this->kabel->id)->value('tipe'))->toBe(TipeMutasiBarang::Pembelian);
});

test('form barang keluar: keperluan bebas, banyak teknisi, dan teknisi opsional bila rusak', function () {
    $this->masuk->execute($this->kabel, TipeMutasiBarang::SaldoAwal, Carbon::today(), 100, $this->admin);

    $form = fn () => Livewire::actingAs($this->admin)->test(Keluar::class)
        ->call('openCreateModal')
        ->set('jenisId', $this->kabel->id)
        ->set('jumlah', 10)
        ->set('keperluan', 'Maintenance POP');

    $form()->call('simpan')->assertHasErrors(['teknisiIds' => 'required']);
    $form()->set('teknisiIds', [$this->teknisiA->id, $this->teknisiB->id])->call('simpan')->assertHasNoErrors();
    $form()->set('rusak', true)->call('simpan')->assertHasNoErrors();

    $pemakaian = MutasiBarang::where('tipe', TipeMutasiBarang::Pemakaian)->firstOrFail();
    expect($pemakaian->keperluan)->toBe('Maintenance POP')
        ->and($pemakaian->teknisi->pluck('id')->sort()->values()->all())->toBe([$this->teknisiA->id, $this->teknisiB->id])
        ->and(MutasiBarang::where('tipe', TipeMutasiBarang::Rusak)->count())->toBe(1);

    Livewire::actingAs($this->admin)->test(Keluar::class)
        ->set('filterTeknisiId', $this->teknisiB->id)
        ->assertSee('Maintenance POP');
});

test('stok awal diisi dari data barang dan membuat unit untuk barang dilacak', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('openCreateModal')
        ->set(['kode' => 'MDM-F670', 'nama' => 'Modem F670', 'formKategoriId' => $this->mdm->id, 'satuan' => 'unit', 'dilacakPerUnit' => true])
        ->set(['stokAwal' => 3, 'tanggalStokAwal' => '2026-09-01'])
        ->call('simpan')
        ->assertHasErrors(['kondisiId' => 'required'])
        ->set('kondisiId', $this->baru->id)
        ->call('simpan')
        ->assertHasNoErrors();

    $modem = JenisBarang::where('kode', 'MDM-F670')->firstOrFail();
    $saldo = MutasiBarang::where('jenis_barang_id', $modem->id)->sole();

    expect($saldo->tipe)->toBe(TipeMutasiBarang::SaldoAwal)
        ->and($saldo->tanggal->toDateString())->toBe('2026-09-01')
        ->and($modem->stok())->toBe(3)
        ->and(UnitBarang::where('jenis_barang_id', $modem->id)->count())->toBe(3);
});

test('unit barang: pilih semua hasil filter lintas halaman lalu cetak label lewat POST', function () {
    $this->masuk->execute($this->modem, TipeMutasiBarang::Pembelian, Carbon::today(), 60, $this->admin, kondisi: $this->baru);
    $rusak = UnitBarang::orderBy('id')->first();
    $this->keluar->execute($this->modem, TipeMutasiBarang::Rusak, Carbon::today(), 0, $this->admin, unitIds: [$rusak->id]);

    $komponen = Livewire::actingAs($this->admin)->test(Unit::class)
        ->set('status', StatusUnitBarang::DiGudang->value)
        ->call('pilihSemua');

    expect($komponen->get('dipilih'))->toHaveCount(59)->not->toContain($rusak->id);

    $this->actingAs($this->admin)
        ->post(route('barang.label'), ['unit' => $komponen->get('dipilih')])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
