<?php

namespace Database\Factories;

use App\Enums\Barang\TipeMutasiBarang;
use App\Models\JenisBarang;
use App\Models\MutasiBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MutasiBarang>
 */
class MutasiBarangFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis_barang_id' => JenisBarang::factory(),
            'arah' => TipeMutasiBarang::Pembelian->arah(),
            'tipe' => TipeMutasiBarang::Pembelian,
            'tanggal' => now()->toDateString(),
            'jumlah' => fake()->numberBetween(1, 20),
        ];
    }

    public function tipe(TipeMutasiBarang $tipe): static
    {
        return $this->state(['tipe' => $tipe, 'arah' => $tipe->arah()]);
    }
}
