<?php

use App\Enums\Wa\KategoriTemplateWa;
use App\Models\WaTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        WaTemplate::firstOrCreate(
            ['kode' => 'tiket_penjadwalan_teknisi'],
            [
                'nama' => 'Penjadwalan Teknisi (Ke Pelanggan)',
                'kategori' => KategoriTemplateWa::Tiket,
                'konten' => "Halo {nama_pelanggan},\n\nKunjungan teknisi untuk tiket {nomor_tiket} telah dijadwalkan pada:\n{jadwal_teknisi}\n\nTeknisi: {nama_pic}\nAlamat: {alamat}\n\nMohon memastikan akses ke lokasi tersedia pada waktu tersebut.\n\nSalam,\n{nama_brand}",
                'keterangan' => 'Dikirimkan ke pelanggan hanya saat teknisi dan jadwal kunjungan sudah ditetapkan.',
                'is_aktif' => true,
            ]
        );
    }

    public function down(): void
    {
        WaTemplate::where('kode', 'tiket_penjadwalan_teknisi')->delete();
    }
};
