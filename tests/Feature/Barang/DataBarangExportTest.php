<?php

use App\Exports\DataBarangExport;
use App\Models\Barang;
use App\Services\Inventaris\BarangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('stok awal, masuk, keluar, and stok akhir are computed correctly for the selected period', function () {
    $service = new BarangService;
    $barang = Barang::factory()->create(['stok' => 0]);

    // Sebelum periode laporan: stok berjalan naik ke 20.
    $service->catatBarangMasuk($barang, 20, Carbon::parse('2026-01-05'));

    // Dalam periode laporan (Februari 2026): masuk 10, keluar 5.
    $service->catatBarangMasuk($barang, 10, Carbon::parse('2026-02-10'));
    $service->catatBarangKeluar($barang, 5, Carbon::parse('2026-02-15'));

    // Setelah periode laporan: seharusnya tidak memengaruhi angka periode Februari.
    $service->catatBarangMasuk($barang, 100, Carbon::parse('2026-03-01'));

    $export = new DataBarangExport(
        startDate: Carbon::parse('2026-02-01'),
        endDate: Carbon::parse('2026-02-28'),
    );

    $row = $export->collection()->firstWhere('id', $barang->id);
    $mapped = $export->map($row);

    [$kode, $nama, $stokAwal, $masuk, $keluar, $stokAkhir] = $mapped;

    expect($stokAwal)->toBe(20)
        ->and($masuk)->toBe(10)
        ->and($keluar)->toBe(5)
        ->and($stokAkhir)->toBe(25);
});
