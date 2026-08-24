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
            // --- 1. Residensial Up To (Broadband / Shared, Priority 7-8) ---
            [
                'nama_paket' => 'Paket Hemat Up To 10 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-UpTo-10M',
                'harga' => 150000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Paket internet ekonomis up to 10 Mbps untuk browsing, sosmed, dan kebutuhan harian.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Family Up To 20 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-UpTo-20M',
                'harga' => 250000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Paket up to 20 Mbps dengan burst hingga 30 Mbps untuk streaming keluarga 3-5 perangkat.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Super Up To 50 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-UpTo-50M',
                'harga' => 375000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Kecepatan up to 50 Mbps dengan burst hingga 75 Mbps untuk download dan streaming 4K.',
                'status' => StatusPaket::Aktif,
            ],

            // --- 2. Residensial 1:1 (Dedicated Home / Gamer / Streamer, Priority 3-5, Flat CIR) ---
            [
                'nama_paket' => 'Paket Home Dedicated 1:1 20 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-Ded-20M',
                'harga' => 350000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Internet residensial dedicated 1:1 murni simetris tanpa pembagian bandwidth dan prioritas antrean tinggi.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Gamer Dedicated 1:1 50 Mbps',
                'profil_bandwidth_nama' => 'Profile-Gamer-Ded-50M',
                'harga' => 550000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Dedicated 1:1 CIR 50 Mbps dengan routing prioritas low latency optimal untuk gaming online kompetitif.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Streamer Dedicated 1:1 100 Mbps',
                'profil_bandwidth_nama' => 'Profile-Streamer-Ded-100M',
                'harga' => 850000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Dedicated 1:1 CIR 100 Mbps simetris tanpa FUP untuk live streaming, content creation, dan heavy upload.',
                'status' => StatusPaket::Aktif,
            ],

            // --- 3. Bisnis / Enterprise 1:1 (Corporate Dedicated, Priority 1-2) ---
            [
                'nama_paket' => 'Paket SOHO Pro Dedicated 50 Mbps',
                'profil_bandwidth_nama' => 'Profile-Biz-50M',
                'harga' => 1250000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Internet bisnis dedicated CIR 1:1 dengan prioritas jaringan tinggi untuk perkantoran dan cafe.',
                'status' => StatusPaket::Aktif,
            ],
            [
                'nama_paket' => 'Paket Corporate Dedicated 100 Mbps',
                'profil_bandwidth_nama' => 'Profile-Biz-100M',
                'harga' => 2500000,
                'masa_aktif_nilai' => 1,
                'masa_aktif_satuan' => MasaAktifSatuan::Bulan,
                'keterangan' => 'Dedicated CIR 1:1 enterprise SLA 99.5% dengan opsi alokasi IP Statis dedicated.',
                'status' => StatusPaket::Aktif,
            ],

            // --- 4. Paket Promo / Legacy (Nonaktif) ---
            [
                'nama_paket' => 'Paket Promo Pelajar 10 Mbps',
                'profil_bandwidth_nama' => 'Profile-Home-UpTo-10M',
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
