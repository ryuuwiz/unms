<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribut identitas Aplikasi Pelanggan per brand (ADR-0066): nama pendek label layar utama
 * dan warna utama. Kosong berarti diturunkan dari brand itu sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pengaturan_prefix_registrasi', 'perusahaan'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->string('nama_pendek', 12)->nullable();
                $table->string('warna_utama', 7)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['pengaturan_prefix_registrasi', 'perusahaan'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropColumn(['nama_pendek', 'warna_utama']);
            });
        }
    }
};
