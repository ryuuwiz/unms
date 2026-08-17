<?php

namespace Database\Factories;

use App\Models\AkunPelanggan;
use App\Models\Pelanggan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AkunPelanggan>
 */
class AkunPelangganFactory extends Factory
{
    protected $model = AkunPelanggan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pelanggan_id' => Pelanggan::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password', // Auto hashed by cast
            'email_verified_at' => now(),
        ];
    }
}
