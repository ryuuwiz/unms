<?php

namespace Database\Seeders;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kota;
use Illuminate\Database\Seeder;

class WilayahSeeder extends Seeder
{
    /**
     * Seed data wilayah yang relevan dengan area coverage ISP.
     * Isi sesuai kota/kabupaten yang dilayani.
     */
    public function run(): void
    {
        $data = $this->wilayahData();

        foreach ($data as $kotaData) {
            $kota = Kota::firstOrCreate(['nama_kota' => $kotaData['nama']]);

            foreach ($kotaData['kecamatan'] as $kecData) {
                $kecamatan = Kecamatan::firstOrCreate([
                    'kota_id' => $kota->id,
                    'nama_kecamatan' => $kecData['nama'],
                ]);

                foreach ($kecData['kelurahan'] as $kelurahan) {
                    Kelurahan::firstOrCreate([
                        'kecamatan_id' => $kecamatan->id,
                        'nama_kelurahan' => $kelurahan,
                    ]);
                }
            }
        }
    }

    /**
     * Data wilayah area coverage ISP.
     * Sesuaikan dengan kota/kabupaten yang dilayani.
     *
     * @return array<int, array{nama: string, kecamatan: array<int, array{nama: string, kelurahan: array<int, string>}>}>
     */
    private function wilayahData(): array
    {
        return [
            // ── Contoh struktur — ganti dengan data kota coverage ISP Anda ──
            // [
            //     'nama' => 'Kota Contoh',
            //     'kecamatan' => [
            //         [
            //             'nama' => 'Kecamatan Contoh',
            //             'kelurahan' => ['Kelurahan A', 'Kelurahan B'],
            //         ],
            //     ],
            // ],
        ];
    }
}
