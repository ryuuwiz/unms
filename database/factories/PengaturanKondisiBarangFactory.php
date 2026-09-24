<?php

namespace Database\Factories;

use App\Models\PengaturanKondisiBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengaturanKondisiBarang>
 */
class PengaturanKondisiBarangFactory extends Factory
{
    protected $model = PengaturanKondisiBarang::class;

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
