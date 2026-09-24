<?php

namespace Database\Factories;

use App\Models\Barang;
use App\Models\PengaturanCabangBarang;
use App\Models\PengaturanJenisBarang;
use App\Models\PengaturanKondisiBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barang>
 */
class BarangFactory extends Factory
{
    protected $model = Barang::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis_barang_id' => PengaturanJenisBarang::factory(),
            'kondisi_barang_id' => PengaturanKondisiBarang::factory(),
            'cabang_barang_id' => PengaturanCabangBarang::factory(),
            'nama_barang' => fake()->words(3, true),
            'satuan' => 'unit',
            'stok' => 0,
            'is_active' => true,
            'keterangan' => null,
        ];
    }
}
