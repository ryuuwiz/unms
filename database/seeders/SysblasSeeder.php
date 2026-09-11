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
        $defaultNumber = config('services.waha.number') ?: '08970919525';
        $defaultHost = config('services.waha.host') ?: 'https://waha.gobilling.id';
        $defaultSession = config('services.waha.session') ?: 'gobilling';
        $defaultApiKey = config('services.waha.api_key') ?: '137ae04e09ee4c668430c660db0741f9';
        $defaultUsername = config('services.waha.username') ?: 'admin';
        $defaultPassword = config('services.waha.password') ?: '81f6bafc11b34793b4349034cbb60178';

        Sysblas::updateOrCreate(
            ['nomor' => $defaultNumber],
            [
                'nama' => 'WAHA Utama (GOBILLING)',
                'provider' => SysblasProvider::Waha,
                'session_name' => $defaultSession,
                'url_api' => $defaultHost,
                'username' => $defaultUsername,
                'password' => $defaultPassword,
                'api_token' => $defaultApiKey,
                'api_secret' => null,
                'limit_per_menit' => 4,
                'is_default' => true,
                'is_aktif' => true,
                'keterangan' => 'Koneksi gateway WhatsApp WAHA utama (session: gobilling) untuk billing, blast notifikasi, dan tiket kendala.',
            ]
        );
    }
}
