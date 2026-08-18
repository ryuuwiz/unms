<?php

namespace Database\Factories;

use App\Models\Kota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kota>
 */
class KotaFactory extends Factory
{
    protected $model = Kota::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_kota' => 'Kota '.fake()->unique()->city(),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
