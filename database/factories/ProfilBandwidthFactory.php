<?php

namespace Database\Factories;

use App\Models\ProfilBandwidth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfilBandwidth>
 */
class ProfilBandwidthFactory extends Factory
{
    protected $model = ProfilBandwidth::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $speedMbps = fake()->randomElement([5, 10, 20, 30, 50, 100]);

        return [
            'nama_bandwidth' => "{$speedMbps}Mbps-".fake()->unique()->numerify('###'),
            'max_limit_tx' => $speedMbps, // Mbps
            'max_limit_rx' => $speedMbps,
            'priority' => 8,
            // burst fields nullable by default
        ];
    }
}
