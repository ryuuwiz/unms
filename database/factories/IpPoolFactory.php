<?php

namespace Database\Factories;

use App\Models\IpPool;
use App\Models\Router;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpPool>
 */
class IpPoolFactory extends Factory
{
    protected $model = IpPool::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $thirdOctet = fake()->unique()->numberBetween(1, 254);

        return [
            'router_id' => Router::factory(),
            'nama_pool' => 'POOL-'.fake()->unique()->numerify('###'),
            'ip_network' => "192.168.{$thirdOctet}.0",
            'cidr' => 24,
            'rentang_ip_awal' => "192.168.{$thirdOctet}.2",
            'rentang_ip_akhir' => "192.168.{$thirdOctet}.254",
            'priority_tx' => 8,
            'priority_rx' => 8,
        ];
    }
}
