<?php

namespace Database\Seeders;

use App\Enums\MasaAktifSatuan;
use App\Enums\StatusPaket;
use App\Models\PaketLayanan;
use App\Models\ProfilBandwidth;
use Illuminate\Database\Seeder;

class PaketLayananSeeder extends Seeder
{
    /**
     * Seed katalog paket layanan internet ISP.
     */
    public function run(): void
    {
        $packages = [
            [
                'nama_paket' => 'Paket Hemat 10 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-10M',
                'harga' => 150000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Paket internet ekonomis untuk browsing, sosmed, dan kebutuhan harian.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Family 20 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-20M',
                'harga' => 250000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Cocok untuk kebutuhan streaming keluarga hingga 3-5 perangkat.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Gamer 50 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-50M',
                'harga' => 400000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Prioritas streaming 4K dan gaming online responsif dengan low latency.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Ultra 100 Mbps',
                'profil_bandwidth_nama' => 'Profile-Gamer-100M',
                'harga' => 650000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Kecepatan ultra untuk download cepat dan streaming multi-perangkat.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket SOHO Pro 50 Mbps',
                'profil_bandwidth_nama' => 'Profile-Biz-50M',
                'harga' => 1000000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Internet bisnis dedicated CIR 1:1 untuk perkantoran dan cafe.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Corporate Dedicated 100 Mbps',
                'profil_bandwidth_nama' => 'Profile-Biz-100M',
                'harga' => 2500000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Dedicated CIR 1:1 dengan SLA 99.5% dan IP Publik Statis untuk enterprise.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Promo Pelajar 10 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-10M',
                'harga' => 100000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Paket promosi khusus program edukasi lama (arsip).',
                'status' => StatusPaket::Nonaktif,
            ],
        ];

        foreach ($packages as $pkg) {
            $profil = ProfilBandwidth::where('nama_bandwidth', $pkg['profil_bandwidth_nama'])->first();

            if ($profil) {
                PaketLayanan::updateOrCreate(
                    ['nama_paket' => $pkg['nama_paket']],
                    [
                        'profil_bandwidth_id' => $profil->id,
                        'harga' => $pkg['harga'],
                        'masa_aktif_nilai' => $pkg['masa_aktif_nilai'],
                        'masa_aktif_satuan' => $pkg['masa_aktif_satuan'],
                        'keterangan' => $pkg['keterangan'],
                        'status' => $pkg['status'],
                    ]
                );
            }
        }
    }
}
