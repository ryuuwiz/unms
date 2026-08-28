<?php

namespace Database\Seeders;

use App\Enums\StatusRouter;
use App\Models\Router;
use Illuminate\Database\Seeder;

class RouterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Router utama untuk core network
        Router::updateOrCreate(
            ['ip_address' => '103.175.156.72'],
            [
                'nama_router' => 'Test Billing',
                'port' => 8728,
                'username' => 'go_billing',
                'password_terenkripsi' => '3Vn5Fd3>:,=cc>X<|5{|0)%qgF7d%#8372KuewGIZ?*QO;#?*B',
                'deskripsi' => 'Router MikroTik Test Billing',
                'status_koneksi' => StatusRouter::Online,
            ]
        );
    }
}
