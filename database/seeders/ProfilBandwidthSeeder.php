<?php

namespace Database\Seeders;

use App\Models\ProfilBandwidth;
use Illuminate\Database\Seeder;

class ProfilBandwidthSeeder extends Seeder
{
    /**
     * Seed profil limit bandwidth MikroTik RouterOS standar ISP.
     */
    public function run(): void
    {
        $profiles = [
            [
                'nama_bandwidth' => 'Profile-Home-10M',
                'max_limit_tx' => 10, // 10 Mbps
                'max_limit_rx' => 10,
                'burst_rate_tx' => null,
                'burst_rate_rx' => null,
                'burst_threshold_tx' => null,
                'burst_threshold_rx' => null,
                'burst_time_tx' => null,
                'burst_time_rx' => null,
                'limit_rate_tx' => 2,
                'limit_rate_rx' => 2,
                'priority' => 8,
            ],
            [
                'nama_bandwidth' => 'Profile-Home-20M',
                'max_limit_tx' => 20, // 20 Mbps
                'max_limit_rx' => 20,
                'burst_rate_tx' => 30,
                'burst_rate_rx' => 30,
                'burst_threshold_tx' => 15,
                'burst_threshold_rx' => 15,
                'burst_time_tx' => 16,
                'burst_time_rx' => 16,
                'limit_rate_tx' => 5,
                'limit_rate_rx' => 5,
                'priority' => 8,
            ],
            [
                'nama_bandwidth' => 'Profile-Home-50M',
                'max_limit_tx' => 50, // 50 Mbps
                'max_limit_rx' => 50,
                'burst_rate_tx' => 75,
                'burst_rate_rx' => 75,
                'burst_threshold_tx' => 38,
                'burst_threshold_rx' => 38,
                'burst_time_tx' => 16,
                'burst_time_rx' => 16,
                'limit_rate_tx' => 10,
                'limit_rate_rx' => 10,
                'priority' => 7,
            ],
            [
                'nama_bandwidth' => 'Profile-Gamer-100M',
                'max_limit_tx' => 100, // 100 Mbps
                'max_limit_rx' => 100,
                'burst_rate_tx' => 120,
                'burst_rate_rx' => 120,
                'burst_threshold_tx' => 80,
                'burst_threshold_rx' => 80,
                'burst_time_tx' => 12,
                'burst_time_rx' => 12,
                'limit_rate_tx' => 20,
                'limit_rate_rx' => 20,
                'priority' => 4,
            ],
            [
                'nama_bandwidth' => 'Profile-Biz-50M',
                'max_limit_tx' => 50, // 50 Mbps Dedicated CIR 1:1
                'max_limit_rx' => 50,
                'burst_rate_tx' => null,
                'burst_rate_rx' => null,
                'burst_threshold_tx' => null,
                'burst_threshold_rx' => null,
                'burst_time_tx' => null,
                'burst_time_rx' => null,
                'limit_rate_tx' => 50,
                'limit_rate_rx' => 50,
                'priority' => 2,
            ],
            [
                'nama_bandwidth' => 'Profile-Biz-100M',
                'max_limit_tx' => 100, // 100 Mbps Dedicated CIR 1:1
                'max_limit_rx' => 100,
                'burst_rate_tx' => null,
                'burst_rate_rx' => null,
                'burst_threshold_tx' => null,
                'burst_threshold_rx' => null,
                'burst_time_tx' => null,
                'burst_time_rx' => null,
                'limit_rate_tx' => 100,
                'limit_rate_rx' => 100,
                'priority' => 1,
            ],
        ];

        foreach ($profiles as $profile) {
            ProfilBandwidth::updateOrCreate(
                ['nama_bandwidth' => $profile['nama_bandwidth']],
                $profile
            );
        }
    }
}
