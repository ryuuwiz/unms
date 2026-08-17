<?php

namespace Database\Seeders;

use App\Models\IpPool;
use App\Models\Router;
use Illuminate\Database\Seeder;

class IpPoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mainRouter = Router::where('ip_address', '192.168.88.1')->first();

        if ($mainRouter) {
            // 1. Pool A (Home)
            IpPool::updateOrCreate(
                [
                    'router_id' => $mainRouter->id,
                    'ip_network' => '10.0.0.0/24',
                ],
                [
                    'name' => 'Pool A (Home)',
                    'cidr' => 24,
                    'ip_range_start' => '10.0.0.2',
                    'ip_range_end' => '10.0.0.254',
                    'queue_tx_mbps' => 20,
                    'queue_rx_mbps' => 20,
                    'priority_tx' => 8,
                    'priority_rx' => 8,
                ]
            );

            // 2. Pool B (Office)
            IpPool::updateOrCreate(
                [
                    'router_id' => $mainRouter->id,
                    'ip_network' => '10.0.1.0/24',
                ],
                [
                    'name' => 'Pool B (Office)',
                    'cidr' => 24,
                    'ip_range_start' => '10.0.1.2',
                    'ip_range_end' => '10.0.1.254',
                    'queue_tx_mbps' => 50,
                    'queue_rx_mbps' => 50,
                    'priority_tx' => 2,
                    'priority_rx' => 2,
                ]
            );
        }

        // Generate additional dummy pools for all other routers
        $otherRouters = Router::where('ip_address', '!=', '192.168.88.1')->get();
        foreach ($otherRouters as $router) {
            IpPool::factory()->count(2)->create([
                'router_id' => $router->id,
            ]);
        }
    }
}
