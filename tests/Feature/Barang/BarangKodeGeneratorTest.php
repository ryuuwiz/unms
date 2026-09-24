<?php

use App\Models\Barang;
use App\Models\PengaturanCabangBarang;
use App\Models\PengaturanJenisBarang;
use App\Models\PengaturanKondisiBarang;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('barang model auto-generates kode_barang with jenis-kondisi-cabang-counter format', function () {
    $jenis = PengaturanJenisBarang::factory()->create(['kode' => 'MDM']);
    $kondisi = PengaturanKondisiBarang::factory()->create(['kode' => 'NEW']);
    $cabang = PengaturanCabangBarang::factory()->create(['kode' => 'BF']);

    $barang1 = Barang::factory()->create([
        'jenis_barang_id' => $jenis->id,
        'kondisi_barang_id' => $kondisi->id,
        'cabang_barang_id' => $cabang->id,
    ]);

    $barang2 = Barang::factory()->create([
        'jenis_barang_id' => $jenis->id,
        'kondisi_barang_id' => $kondisi->id,
        'cabang_barang_id' => $cabang->id,
    ]);

    expect($barang1->kode_barang)->toBe('MDM-NEW-BF-001')
        ->and($barang2->kode_barang)->toBe('MDM-NEW-BF-002');
});

test('kode_barang counter is scoped per jenis-kondisi-cabang combination', function () {
    $jenis = PengaturanJenisBarang::factory()->create(['kode' => 'MDM']);
    $kondisiNew = PengaturanKondisiBarang::factory()->create(['kode' => 'NEW']);
    $kondisiPgt = PengaturanKondisiBarang::factory()->create(['kode' => 'PGT']);
    $cabang = PengaturanCabangBarang::factory()->create(['kode' => 'BF']);

    $barangNew = Barang::factory()->create([
        'jenis_barang_id' => $jenis->id,
        'kondisi_barang_id' => $kondisiNew->id,
        'cabang_barang_id' => $cabang->id,
    ]);

    $barangPgt = Barang::factory()->create([
        'jenis_barang_id' => $jenis->id,
        'kondisi_barang_id' => $kondisiPgt->id,
        'cabang_barang_id' => $cabang->id,
    ]);

    expect($barangNew->kode_barang)->toBe('MDM-NEW-BF-001')
        ->and($barangPgt->kode_barang)->toBe('MDM-PGT-BF-001');
});

test('kode_barang provided explicitly is not overwritten', function () {
    $barang = Barang::factory()->create(['kode_barang' => 'MDM-NEW-BF-999']);

    expect($barang->kode_barang)->toBe('MDM-NEW-BF-999');
});
