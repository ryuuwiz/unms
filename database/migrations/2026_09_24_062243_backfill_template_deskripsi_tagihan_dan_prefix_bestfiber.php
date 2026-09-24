<?php

use App\Models\PengaturanPrefixRegistrasi;
use App\Models\TemplateDeskripsiTagihan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill data untuk DB yang sudah berjalan -- lihat CONTEXT.md "Template Deskripsi Tagihan
     * Gateway": (1) template default bila belum ada satu pun; (2) nama Prefix Registrasi `BF`
     * dari nilai seeder lama "Bestfiber" menjadi "BESTFIBER". Nama yang sudah diubah admin
     * (bukan persis "Bestfiber") tidak disentuh. Link bayar gateway yang sudah terbit tidak bisa
     * diubah deskripsinya (Xendit tidak menyediakan update); hanya link baru yang memakai template.
     */
    public function up(): void
    {
        if (! TemplateDeskripsiTagihan::query()->where('is_default', true)->exists()) {
            TemplateDeskripsiTagihan::create([
                'nama' => 'Default Pembayaran Internet',
                'konten' => TemplateDeskripsiTagihan::KONTEN_DEFAULT,
                'is_default' => true,
            ]);
        }

        PengaturanPrefixRegistrasi::where('kode', 'BF')->where('nama', 'Bestfiber')->update(['nama' => 'BESTFIBER']);
    }

    public function down(): void
    {
        // Data backfill; tidak dipulihkan.
    }
};
