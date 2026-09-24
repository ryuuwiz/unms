<?php

namespace Database\Factories;

use App\Models\KondisiBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KondisiBarang>
 */
class KondisiBarangFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->lexify('???')),
            'nama' => ucfirst(fake()->word()),
        ];
    }
}
