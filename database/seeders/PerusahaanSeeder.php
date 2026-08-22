<?php

namespace Database\Seeders;

use App\Models\Perusahaan;
use Illuminate\Database\Seeder;

class PerusahaanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Perusahaan::firstOrCreate(
            ['is_default' => true],
            [
                'nama_perusahaan' => 'PT GOBILLING NUSANTARA TEKNOLOGI',
                'nama_brand' => 'GOBILLING',
                'tagline' => 'Solusi Billing & Manajemen ISP Terpadu',
                'alamat' => 'Jl. Boulevard Utama No. 88, Kawasan Cyber Tech',
                'kota' => 'Jakarta Selatan',
                'kode_pos' => '12930',
                'telepon' => '021-5551234',
                'whatsapp' => '0812-3456-7890',
                'email' => 'billing@gobilling.id',
                'website' => 'https://gobilling.id',
                'npwp' => '01.234.567.8-901.000',
                'nama_bank' => 'Bank Central Asia (BCA)',
                'nomor_rekening' => '8830123456',
                'atas_nama' => 'PT GOBILLING NUSANTARA TEKNOLOGI',
                'catatan_invoice' => 'Terima kasih atas kepercayaan Anda menggunakan layanan internet GOBILLING. Mohon lakukan pembayaran sebelum jatuh tempo untuk kenyamanan akses internet Anda.',
                'syarat_ketentuan' => '1. Pembayaran yang telah dilakukan tidak dapat dikembalikan. 2. Layanan akan otomatis terisolir jika tagihan belum diselesaikan hingga batas tanggal jatuh tempo.',
                'nama_penandatangan' => 'Rian Hidayat, S.Kom',
                'jabatan_penandatangan' => 'Head of Finance & Billing',
                'is_default' => true,
            ]
        );
    }
}
