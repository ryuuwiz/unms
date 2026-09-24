<?php

namespace Database\Factories;

use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisBarang>
 */
class JenisBarangFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kategori_barang_id' => KategoriBarang::factory(),
            'kode' => strtoupper(fake()->unique()->bothify('BRG-###??')),
            'nama' => ucfirst(fake()->word()).' '.fake()->numerify('##'),
            'satuan' => 'pcs',
            'dilacak_per_unit' => false,
        ];
    }

    public function dilacak(): static
    {
        return $this->state(['dilacak_per_unit' => true, 'satuan' => 'unit']);
    }
}
