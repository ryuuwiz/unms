<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Template disposisi teknisi menyebut Brand Pelanggan (ADR-0061). Hanya baris bawaan
     * seeder yang diubah; template yang sudah diedit admin tanpa baris itu tidak disentuh.
     */
    public function up(): void
    {
        DB::table('wa_template')
            ->where('kode', 'tiket_penugasan_teknisi')
            ->update(['konten' => DB::raw("REPLACE(konten, 'Pelanggan: {nama_pelanggan} ({no_hp_pelanggan})', 'Pelanggan: {nama_pelanggan} - {nama_brand} ({no_hp_pelanggan})')")]);
    }

    public function down(): void
    {
        DB::table('wa_template')
            ->where('kode', 'tiket_penugasan_teknisi')
            ->update(['konten' => DB::raw("REPLACE(konten, 'Pelanggan: {nama_pelanggan} - {nama_brand} ({no_hp_pelanggan})', 'Pelanggan: {nama_pelanggan} ({no_hp_pelanggan})')")]);
    }
};
