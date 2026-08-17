<?php

namespace Database\Seeders;

use App\Enums\StatusOdpPort;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\Perumahan;
use Illuminate\Database\Seeder;

class OdpSeeder extends Seeder
{
    /**
     * Seed titik distribusi ODP (Optical Distribution Point) dan port-nya.
     */
    public function run(): void
    {
        $perumahans = Perumahan::all();

        foreach ($perumahans as $perumahan) {
            $singkatan = $perumahan->singkatan ?: strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $perumahan->nama_perumahan), 0, 3));

            // Buat 2 ODP untuk setiap perumahan
            for ($i = 1; $i <= 2; $i++) {
                $namaOdp = sprintf('ODP-%s-%02d', $singkatan, $i);
                $kapasitas = ($i === 1) ? 16 : 8;

                $odp = Odp::firstOrCreate(
                    ['nama_odp' => $namaOdp],
                    [
                        'perumahan_id' => $perumahan->id,
                        'kapasitas_port' => $kapasitas,
                        'latitude' => -6.9175 + (fake()->randomFloat(5, -0.05, 0.05)),
                        'longitude' => 107.6191 + (fake()->randomFloat(5, -0.05, 0.05)),
                    ]
                );

                // Buat slot port untuk ODP tersebut jika belum ada
                for ($portNum = 1; $portNum <= $kapasitas; $portNum++) {
                    OdpPort::firstOrCreate(
                        [
                            'odp_id' => $odp->id,
                            'nomor_port' => $portNum,
                        ],
                        [
                            'status' => StatusOdpPort::Kosong,
                            'layanan_pelanggan_id' => null,
                        ]
                    );
                }
            }
        }
    }
}
