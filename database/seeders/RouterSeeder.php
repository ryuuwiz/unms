<?php

namespace Database\Seeders;

use App\Enums\RouterStatus;
use App\Models\Router;
use Illuminate\Database\Seeder;

class RouterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create main specific router
        Router::updateOrCreate(
            ['ip_address' => '192.168.88.1'],
            [
                'name' => 'Main Router',
                'api_port' => 8728,
                'username' => 'admin',
                'password' => 'password',
                'description' => 'Main MikroTik Router for Core Network',
                'status' => RouterStatus::Online,
            ]
        );

        // 2. Generate dummy data for load testing
        Router::factory(4)->create();
    }
}
