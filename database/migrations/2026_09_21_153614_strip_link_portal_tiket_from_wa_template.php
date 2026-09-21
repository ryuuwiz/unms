<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Halaman tiket Portal Pelanggan dihapus (ADR-0040); buang baris berisi
     * placeholder {link_portal_tiket} dari template WA yang sudah tersimpan agar
     * tidak terkirim sebagai teks mentah.
     */
    public function up(): void
    {
        DB::table('wa_template')
            ->where('konten', 'like', '%{link_portal_tiket}%')
            ->get(['id', 'konten'])
            ->each(function (object $template): void {
                $lines = array_filter(
                    explode("\n", $template->konten),
                    fn (string $line): bool => ! str_contains($line, '{link_portal_tiket}'),
                );
                $konten = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

                DB::table('wa_template')->where('id', $template->id)->update(['konten' => $konten]);
            });
    }

    public function down(): void
    {
        // Tidak dapat dipulihkan: baris yang dibuang tidak disimpan.
    }
};
