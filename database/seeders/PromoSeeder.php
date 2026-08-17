<?php

namespace Database\Seeders;

use App\Enums\DiskonTipe;
use App\Enums\JenisPromo;
use App\Models\Promo;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    /**
     * Seed katalog promosi ISP.
     */
    public function run(): void
    {
        $promos = [
            [
                'kode_promo' => 'DISKON20',
                'nama_promo' => 'Diskon 20% Pelanggan Baru',
                'jenis' => JenisPromo::Diskon,
                'deskripsi' => 'Diskon 20% untuk pembayaran invoice pertama pelanggan baru.',
                'aturan' => 'Berlaku 1x per pelanggan untuk seluruh pilihan paket internet rumah.',
                'bayar_bulan' => null,
                'bonus_bulan' => null,
                'diskon_tipe' => DiskonTipe::Persentase,
                'diskon_nilai' => 20,
                'minimal_nominal_invoice' => 150000,
                'kuota_global' => 100,
                'kuota_per_pelanggan' => 1,
                'terpakai_global' => 0,
                'berlaku_dari' => now()->startOfYear(),
                'berlaku_sampai' => now()->endOfYear(),
                'aktif' => true,
                'tampil_ke_customer' => true,
            ],
            [
                'kode_promo' => 'HEMAT50RB',
                'nama_promo' => 'Potongan Langsung Rp 50.000',
                'jenis' => JenisPromo::Diskon,
                'deskripsi' => 'Potongan langsung Rp 50.000 untuk paket minimal Family 20 Mbps.',
                'aturan' => 'Minimal total tagihan invoice Rp 250.000.',
                'bayar_bulan' => null,
                'bonus_bulan' => null,
                'diskon_tipe' => DiskonTipe::Nominal,
                'diskon_nilai' => 50000,
                'minimal_nominal_invoice' => 250000,
                'kuota_global' => 50,
                'kuota_per_pelanggan' => 1,
                'terpakai_global' => 0,
                'berlaku_dari' => now()->startOfMonth(),
                'berlaku_sampai' => now()->addMonths(3),
                'aktif' => true,
                'tampil_ke_customer' => true,
            ],
            [
                'kode_promo' => 'PAY6GET1',
                'nama_promo' => 'Promo Bayar 6 Bulan Bonus 1 Bulan',
                'jenis' => JenisPromo::BonusDurasi,
                'deskripsi' => 'Bayar 6 bulan di muka, dapatkan ekstra 1 bulan langganan gratis.',
                'aturan' => 'Hanya berlaku untuk pembayaran di muka via admin atau transfer bank.',
                'bayar_bulan' => 6,
                'bonus_bulan' => 1,
                'diskon_tipe' => null,
                'diskon_nilai' => null,
                'minimal_nominal_invoice' => null,
                'kuota_global' => 200,
                'kuota_per_pelanggan' => 2,
                'terpakai_global' => 0,
                'berlaku_dari' => now()->startOfYear(),
                'berlaku_sampai' => now()->endOfYear(),
                'aktif' => true,
                'tampil_ke_customer' => true,
            ],
            [
                'kode_promo' => 'PAY12GET2',
                'nama_promo' => 'Promo Bayar 12 Bulan Bonus 2 Bulan',
                'jenis' => JenisPromo::BonusDurasi,
                'deskripsi' => 'Bayar 12 bulan di muka, dapatkan ekstra 2 bulan langganan gratis.',
                'aturan' => 'Hanya berlaku untuk paket tahunan perumahan & SOHO.',
                'bayar_bulan' => 12,
                'bonus_bulan' => 2,
                'diskon_tipe' => null,
                'diskon_nilai' => null,
                'minimal_nominal_invoice' => null,
                'kuota_global' => 100,
                'kuota_per_pelanggan' => 1,
                'terpakai_global' => 0,
                'berlaku_dari' => now()->startOfYear(),
                'berlaku_sampai' => now()->endOfYear(),
                'aktif' => true,
                'tampil_ke_customer' => true,
            ],
            [
                'kode_promo' => 'KEMERDEKAAN',
                'nama_promo' => 'Promo Kemerdekaan RI (Arsip)',
                'jenis' => JenisPromo::Diskon,
                'deskripsi' => 'Promo perayaan hari kemerdekaan tahun lalu.',
                'aturan' => 'Diskon 17% all package.',
                'bayar_bulan' => null,
                'bonus_bulan' => null,
                'diskon_tipe' => DiskonTipe::Persentase,
                'diskon_nilai' => 17,
                'minimal_nominal_invoice' => 100000,
                'kuota_global' => 100,
                'kuota_per_pelanggan' => 1,
                'terpakai_global' => 100,
                'berlaku_dari' => now()->subYear()->startOfMonth(),
                'berlaku_sampai' => now()->subYear()->endOfMonth(),
                'aktif' => false,
                'tampil_ke_customer' => false,
            ],
        ];

        foreach ($promos as $promo) {
            Promo::updateOrCreate(
                ['kode_promo' => $promo['kode_promo']],
                $promo
            );
        }
    }
}
