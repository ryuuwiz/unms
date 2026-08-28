<?php

namespace Database\Seeders;

use App\Enums\Sysblas\SysblasProvider;
use App\Models\Sysblas;
use Illuminate\Database\Seeder;

class SysblasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultNumber = config('services.wablas.number', '08970919525');
        $defaultHost = config('services.wablas.host', 'https://tegal.wablas.com');
        $defaultToken = config('services.wablas.token', 'test_token');
        $defaultSecret = config('services.wablas.secret', 'test_secret');

        Sysblas::updateOrCreate(
            ['nomor' => $defaultNumber],
            [
                'nama' => 'WABLAS Utama (Billing & Tiket)',
                'provider' => SysblasProvider::Wablas,
                'url_api' => $defaultHost,
                'api_token' => $defaultToken,
                'api_secret' => $defaultSecret,
                'limit_per_menit' => 25,
                'is_default' => true,
                'is_aktif' => true,
                'keterangan' => 'Koneksi gateway WhatsApp WABLAS utama untuk pengingat tagihan dan notifikasi tiket.',
            ]
        );
    }
}
