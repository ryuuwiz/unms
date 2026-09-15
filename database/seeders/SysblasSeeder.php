<?php

namespace Database\Seeders;

use App\Enums\Sysblas\SysblasProvider;
use App\Models\Sysblas;
use Illuminate\Database\Seeder;

class SysblasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Gateway WhatsApp aplikasi ini sudah sepenuhnya pindah ke GOWA (self-hosted,
     * gowa.gobilling.id) — WAHA tidak lagi dipakai, jadi seeder ini hanya menyediakan
     * koneksi GOWA default.
     */
    public function run(): void
    {
        $defaultNumber = config('services.gowa.number') ?: '628970919525';
        $defaultHost = config('services.gowa.host') ?: 'https://gowa.gobilling.id';
        $defaultUsername = config('services.gowa.username') ?: '';
        $defaultPassword = config('services.gowa.password') ?: '';
        $defaultDeviceId = config('services.gowa.device_id') ?: 'default';

        // Device ID GOWA dipasangkan manual lewat dashboard GOWA (gowa-ui), bukan digenerate
        // aplikasi ini — jangan timpa session_name nyata yang sudah tersimpan.
        $existingDeviceId = Sysblas::where('nomor', $defaultNumber)->value('session_name');

        Sysblas::updateOrCreate(
            ['nomor' => $defaultNumber],
            [
                'nama' => 'GOWA Utama (GOBILLING)',
                'provider' => SysblasProvider::Gowa,
                'session_name' => $existingDeviceId ?: $defaultDeviceId,
                'url_api' => $defaultHost,
                'username' => $defaultUsername,
                'password' => $defaultPassword,
                'api_token' => null,
                'api_secret' => null,
                'limit_per_menit' => 4,
                'delay_detik' => 15,
                'is_default' => true,
                'is_aktif' => true,
                'keterangan' => 'Koneksi gateway WhatsApp GOWA utama untuk billing, blast notifikasi, dan tiket kendala.',
            ]
        );
    }
}
