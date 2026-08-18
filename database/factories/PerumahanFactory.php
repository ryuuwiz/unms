<?php

namespace Database\Factories;

use App\Models\Kelurahan;
use App\Models\Perumahan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Perumahan>
 */
class PerumahanFactory extends Factory
{
    protected $model = Perumahan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kelurahan_id' => Kelurahan::factory(),
            'nama_perumahan' => fake()->unique()->company().' Residence',
            'singkatan' => strtoupper(fake()->lexify('???')),
            'latitude' => fake()->latitude(-6.99, -6.85),
            'longitude' => fake()->longitude(107.50, 107.75),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
