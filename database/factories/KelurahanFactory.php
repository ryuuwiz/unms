<?php

namespace Database\Factories;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kelurahan>
 */
class KelurahanFactory extends Factory
{
    protected $model = Kelurahan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kecamatan_id' => Kecamatan::factory(),
            'nama_kelurahan' => fake()->unique()->streetName(),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
