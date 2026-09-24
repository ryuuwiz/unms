<?php

use App\Models\Barang;
use App\Services\Inventaris\BarangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new BarangService;
});

test('catatBarangMasuk increments stok and records the transaction', function () {
    $barang = Barang::factory()->create(['stok' => 10]);

    $barangMasuk = $this->service->catatBarangMasuk(
        barang: $barang,
        jumlah: 5,
        tanggal: Carbon::today(),
        keterangan: 'Pembelian supplier',
    );

    expect($barangMasuk->jumlah_masuk)->toBe(5)
        ->and($barang->fresh()->stok)->toBe(15);
});

test('catatBarangKeluar decrements stok and records the transaction', function () {
    $barang = Barang::factory()->create(['stok' => 10]);

    $barangKeluar = $this->service->catatBarangKeluar(
        barang: $barang,
        jumlah: 4,
        tanggal: Carbon::today(),
        teknisi: 'Budi',
    );

    expect($barangKeluar->jumlah_keluar)->toBe(4)
        ->and($barangKeluar->teknisi)->toBe('Budi')
        ->and($barang->fresh()->stok)->toBe(6);
});

test('catatBarangKeluar throws when stok is insufficient', function () {
    $barang = Barang::factory()->create(['stok' => 2]);

    $this->service->catatBarangKeluar(
        barang: $barang,
        jumlah: 5,
        tanggal: Carbon::today(),
    );
})->throws(RuntimeException::class);

test('catatBarangKeluar does not change stok when it throws', function () {
    $barang = Barang::factory()->create(['stok' => 2]);

    try {
        $this->service->catatBarangKeluar(
            barang: $barang,
            jumlah: 5,
            tanggal: Carbon::today(),
        );
    } catch (RuntimeException $e) {
        // expected
    }

    expect($barang->fresh()->stok)->toBe(2)
        ->and($barang->fresh()->barangKeluars()->count())->toBe(0);
});
