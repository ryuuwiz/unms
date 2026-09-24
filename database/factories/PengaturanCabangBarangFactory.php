<?php

namespace Database\Factories;

use App\Models\PengaturanCabangBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengaturanCabangBarang>
 */
class PengaturanCabangBarangFactory extends Factory
{
    protected $model = PengaturanCabangBarang::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('??')),
            'nama' => fake()->city(),
            'is_active' => true,
        ];
    }
}
