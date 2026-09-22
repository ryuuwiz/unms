<?php

namespace Database\Seeders;

use App\Models\PengaturanPrefixRegistrasi;
use Illuminate\Database\Seeder;

class PengaturanPrefixRegistrasiSeeder extends Seeder
{
    /**
     * Seed daftar prefix No. Registrasi pelanggan.
     */
    public function run(): void
    {
        foreach ([
            'BF' => 'Bestfiber',
            'ARS' => 'Arsyila',
        ] as $kode => $nama) {
            PengaturanPrefixRegistrasi::firstOrCreate(['kode' => $kode], ['nama' => $nama, 'is_active' => true]);
        }
    }
}
