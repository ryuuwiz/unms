<?php

namespace Database\Factories;

use App\Models\PengaturanJenisBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengaturanJenisBarang>
 */
class PengaturanJenisBarangFactory extends Factory
{
    protected $model = PengaturanJenisBarang::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('???')),
            'nama' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
