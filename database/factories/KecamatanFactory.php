<?php

namespace Database\Factories;

use App\Models\Kecamatan;
use App\Models\Kota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kecamatan>
 */
class KecamatanFactory extends Factory
{
    protected $model = Kecamatan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kota_id' => Kota::factory(),
            'nama_kecamatan' => fake()->unique()->streetName(),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
