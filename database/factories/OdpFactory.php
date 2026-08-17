<?php

namespace Database\Factories;

use App\Models\Odp;
use App\Models\Perumahan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Odp>
 */
class OdpFactory extends Factory
{
    protected $model = Odp::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_odp' => 'ODP-'.strtoupper(fake()->unique()->bothify('???-##')),
            'perumahan_id' => Perumahan::factory(),
            'kapasitas_port' => fake()->randomElement([8, 16]),
            'latitude' => fake()->latitude(-6.95, -6.88),
            'longitude' => fake()->longitude(107.55, 107.68),
        ];
    }
}
