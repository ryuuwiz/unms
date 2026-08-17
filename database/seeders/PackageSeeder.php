<?php

namespace Database\Seeders;

use App\Enums\PackageStatus;
use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Home 10 Mbps',
                'download_speed_mbps' => 10,
                'upload_speed_mbps' => 5,
                'price' => 150000,
                'description' => 'Paket internet rumah hemat untuk browsing, media sosial, dan streaming ringan.',
                'status' => PackageStatus::Active,
            ],
            [
                'name' => 'Home 20 Mbps',
                'download_speed_mbps' => 20,
                'upload_speed_mbps' => 10,
                'price' => 200000,
                'description' => 'Paket keluarga ideal untuk streaming HD, video conference, dan game online.',
                'status' => PackageStatus::Active,
            ],
            [
                'name' => 'Home 50 Mbps',
                'download_speed_mbps' => 50,
                'upload_speed_mbps' => 25,
                'price' => 350000,
                'description' => 'Kecepatan tinggi untuk banyak perangkat, download file besar, dan streaming 4K tanpa hambatan.',
                'status' => PackageStatus::Active,
            ],
            [
                'name' => 'Business 100 Mbps (1:1)',
                'download_speed_mbps' => 100,
                'upload_speed_mbps' => 100,
                'price' => 750000,
                'description' => 'Dedicated bandwidth simetris 1:1 untuk kantor, kafe, dan kebutuhan server dengan IP publik gratis.',
                'status' => PackageStatus::Active,
            ],
            [
                'name' => 'Legacy Home 5 Mbps',
                'download_speed_mbps' => 5,
                'upload_speed_mbps' => 2,
                'price' => 100000,
                'description' => 'Paket lama yang sudah tidak dijual untuk pelanggan baru.',
                'status' => PackageStatus::Inactive,
            ],
        ];

        foreach ($packages as $pkg) {
            Package::firstOrCreate(
                ['name' => $pkg['name']],
                $pkg
            );
        }
    }
}
