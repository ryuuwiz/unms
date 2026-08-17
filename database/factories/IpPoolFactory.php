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
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseIp = $this->faker->ipv4();
        $octets = explode('.', $baseIp);
        $octets[3] = '0';
        $network = implode('.', $octets).'/24';

        $start = implode('.', [$octets[0], $octets[1], $octets[2], '2']);
        $end = implode('.', [$octets[0], $octets[1], $octets[2], '254']);

        return [
            'router_id' => Router::factory(),
            'name' => 'Pool '.$this->faker->words(2, true),
            'ip_network' => $network,
            'cidr' => 24,
            'ip_range_start' => $start,
            'ip_range_end' => $end,
            'queue_tx_mbps' => $this->faker->randomElement([5, 10, 20, 50, 100]),
            'queue_rx_mbps' => $this->faker->randomElement([5, 10, 20, 50, 100]),
            'priority_tx' => $this->faker->numberBetween(1, 8),
            'priority_rx' => $this->faker->numberBetween(1, 8),
        ];
    }
}
