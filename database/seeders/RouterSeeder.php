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
            ['ip_address' => '192.168.88.1'],
            [
                'nama_router' => 'Main Router',
                'port' => 8728,
                'username' => 'admin',
                'password_terenkripsi' => 'password',
                'deskripsi' => 'Router MikroTik utama untuk Core Network',
                'status_koneksi' => StatusRouter::Online,
            ]
        );

        // Data dummy untuk testing jika belum ada
        if (Router::count() <= 1) {
            Router::factory(4)->create();
        }
    }
}
