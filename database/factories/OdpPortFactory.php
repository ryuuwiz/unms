<?php

namespace Database\Factories;

use App\Enums\StatusOdpPort;
use App\Models\Odp;
use App\Models\OdpPort;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OdpPort>
 */
class OdpPortFactory extends Factory
{
    protected $model = OdpPort::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'odp_id' => Odp::factory(),
            'nomor_port' => fake()->numberBetween(1, 8),
            'status' => StatusOdpPort::Kosong,
            'layanan_pelanggan_id' => null,
        ];
    }
}
