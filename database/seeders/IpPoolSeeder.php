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
            // Pool A — Pelanggan Rumahan
            IpPool::updateOrCreate(
                [
                    'router_id' => $mainRouter->id,
                    'ip_network' => '10.0.0.0',
                ],
                [
                    'nama_pool' => 'Pool-Rumah',
                    'cidr' => 24,
                    'rentang_ip_awal' => '10.0.0.2',
                    'rentang_ip_akhir' => '10.0.0.254',
                    'priority_tx' => 8,
                    'priority_rx' => 8,
                ]
            );

            // Pool B — Pelanggan Bisnis (prioritas lebih tinggi)
            IpPool::updateOrCreate(
                [
                    'router_id' => $mainRouter->id,
                    'ip_network' => '10.0.1.0',
                ],
                [
                    'nama_pool' => 'Pool-Bisnis',
                    'cidr' => 24,
                    'rentang_ip_awal' => '10.0.1.2',
                    'rentang_ip_akhir' => '10.0.1.254',
                    'priority_tx' => 2,
                    'priority_rx' => 2,
                ]
            );
        }

        // Pool dummy untuk router lain jika belum memiliki pool
        $otherRouters = Router::where('ip_address', '!=', '192.168.88.1')->get();
        foreach ($otherRouters as $router) {
            if ($router->ipPools()->count() === 0) {
                IpPool::factory()->count(2)->create([
                    'router_id' => $router->id,
                ]);
            }
        }
    }
}
