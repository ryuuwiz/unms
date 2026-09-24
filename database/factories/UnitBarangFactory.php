<?php

namespace Database\Factories;

use App\Enums\Barang\StatusUnitBarang;
use App\Models\JenisBarang;
use App\Models\KondisiBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitBarang>
 */
class UnitBarangFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis_barang_id' => JenisBarang::factory()->dilacak(),
            'kode' => 'TST-'.fake()->unique()->numerify('#####'),
            'kondisi_barang_id' => KondisiBarang::factory(),
            'prefix_registrasi_id' => null,
            'status' => StatusUnitBarang::DiGudang,
            'serial_number' => null,
        ];
    }
}
