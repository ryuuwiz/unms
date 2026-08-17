<?php

namespace Database\Seeders;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kota;
use App\Models\Perumahan;
use Illuminate\Database\Seeder;

class WilayahSeeder extends Seeder
{
    /**
     * Seed data wilayah yang relevan dengan area coverage ISP.
     * Mengisi data Kota, Kecamatan, Kelurahan, dan Perumahan.
     */
    public function run(): void
    {
        $data = $this->wilayahData();

        foreach ($data as $kotaData) {
            $kota = Kota::firstOrCreate(
                ['nama_kota' => $kotaData['nama']],
                ['keterangan' => $kotaData['keterangan'] ?? null]
            );

            foreach ($kotaData['kecamatan'] as $kecData) {
                $kecamatan = Kecamatan::firstOrCreate(
                    [
                        'kota_id' => $kota->id,
                        'nama_kecamatan' => $kecData['nama'],
                    ],
                    ['keterangan' => $kecData['keterangan'] ?? null]
                );

                foreach ($kecData['kelurahan'] as $kelData) {
                    $kelurahan = Kelurahan::firstOrCreate(
                        [
                            'kecamatan_id' => $kecamatan->id,
                            'nama_kelurahan' => $kelData['nama'],
                        ],
                        ['keterangan' => $kelData['keterangan'] ?? null]
                    );

                    if (! empty($kelData['perumahan'])) {
                        foreach ($kelData['perumahan'] as $perumahanData) {
                            Perumahan::firstOrCreate(
                                [
                                    'kelurahan_id' => $kelurahan->id,
                                    'nama_perumahan' => $perumahanData['nama'],
                                ],
                                [
                                    'singkatan' => $perumahanData['singkatan'] ?? null,
                                    'keterangan' => $perumahanData['keterangan'] ?? null,
                                ]
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Data wilayah area coverage ISP.
     *
     * @return array<int, array{nama: string, keterangan?: string, kecamatan: array<int, array{nama: string, keterangan?: string, kelurahan: array<int, array{nama: string, keterangan?: string, perumahan?: array<int, array{nama: string, singkatan?: string, keterangan?: string}>}>}>}>
     */
    private function wilayahData(): array
    {
        return [
            [
                'nama' => 'Kota Bandung',
                'keterangan' => 'Area coverage Core Fiber Ring Bandung',
                'kecamatan' => [
                    [
                        'nama' => 'Buahbatu',
                        'keterangan' => 'Area cluster residensial Bandung Selatan',
                        'kelurahan' => [
                            [
                                'nama' => 'Margasari',
                                'perumahan' => [
                                    ['nama' => 'Griya Bandung Indah', 'singkatan' => 'GBI', 'keterangan' => 'Cluster Blok A-H'],
                                    ['nama' => 'Margahayu Raya Barat', 'singkatan' => 'MHR', 'keterangan' => 'Cluster Blok L-R'],
                                ],
                            ],
                            [
                                'nama' => 'Sekejati',
                                'perumahan' => [
                                    ['nama' => 'Bumi Adipura', 'singkatan' => 'BAP', 'keterangan' => 'Cluster Pinus & Cendana'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'nama' => 'Lengkong',
                        'keterangan' => 'Area komersial & residensial Bandung Pusat',
                        'kelurahan' => [
                            [
                                'nama' => 'Turangga',
                                'perumahan' => [
                                    ['nama' => 'Batununggal Indah', 'singkatan' => 'BTI', 'keterangan' => 'Perumahan Elit Batununggal'],
                                    ['nama' => 'Turangga Asri', 'singkatan' => 'TGA', 'keterangan' => 'Cluster Townhouse'],
                                ],
                            ],
                            [
                                'nama' => 'Malabar',
                                'perumahan' => [
                                    ['nama' => 'Komp. Malabar Permai', 'singkatan' => 'MBP', 'keterangan' => 'Perumahan Malabar'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'nama' => 'Coblong',
                        'keterangan' => 'Area kampus ITB & residensial Dago',
                        'kelurahan' => [
                            [
                                'nama' => 'Dago',
                                'perumahan' => [
                                    ['nama' => 'Dago Village Resort', 'singkatan' => 'DVR', 'keterangan' => 'Residensi Dago Atas'],
                                    ['nama' => 'Cisitu Indah', 'singkatan' => 'CSI', 'keterangan' => 'Cluster Cisitu'],
                                ],
                            ],
                            [
                                'nama' => 'Sadang Serang',
                                'perumahan' => [
                                    ['nama' => 'Sadang Hegar Residence', 'singkatan' => 'SHR', 'keterangan' => 'Komp. Sadang Hegar'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'nama' => 'Kota Cimahi',
                'keterangan' => 'Area coverage Node Metro Cimahi',
                'kecamatan' => [
                    [
                        'nama' => 'Cimahi Utara',
                        'kelurahan' => [
                            [
                                'nama' => 'Cibabat',
                                'perumahan' => [
                                    ['nama' => 'Pesona Cibabat Indah', 'singkatan' => 'PCI', 'keterangan' => 'Cluster Cibabat Regency'],
                                    ['nama' => 'Permata Cimahi', 'singkatan' => 'PTC', 'keterangan' => 'Komp. Permata Blok B-D'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'nama' => 'Cimahi Tengah',
                        'kelurahan' => [
                            [
                                'nama' => 'Baros',
                                'perumahan' => [
                                    ['nama' => 'Baros City View', 'singkatan' => 'BCV', 'keterangan' => 'Townhouse Baros'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'nama' => 'Kabupaten Bandung Barat',
                'keterangan' => 'Area coverage Fiber To The Home KBP',
                'kecamatan' => [
                    [
                        'nama' => 'Padalarang',
                        'kelurahan' => [
                            [
                                'nama' => 'Kertajaya',
                                'perumahan' => [
                                    ['nama' => 'Kota Baru Parahyangan', 'singkatan' => 'KBP', 'keterangan' => 'Tatar Wangsakancana & Jingganagara'],
                                    ['nama' => 'Bumi Parahyangan Regency', 'singkatan' => 'BPR', 'keterangan' => 'Cluster Asri Padalarang'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
