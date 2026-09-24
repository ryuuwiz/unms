<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventaris barang -- lihat ADR-0057 dan CONTEXT.md "Jenis Barang" / "Unit Barang" / "Kode Barang".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5)->unique();
            $table->string('nama', 100);
            $table->timestamps();
        });

        Schema::create('kondisi_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5)->unique();
            $table->string('nama', 100);
            $table->timestamps();
        });

        Schema::create('jenis_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_barang_id')->constrained('kategori_barang');
            $table->string('kode', 40)->unique();
            $table->string('nama', 150);
            $table->string('satuan', 20)->default('pcs');
            $table->boolean('dilacak_per_unit')->default(false);
            $table->timestamps();
        });

        Schema::create('kode_barang_counter', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 40)->unique();
            $table->unsignedInteger('nomor_terakhir')->default(0);
            $table->timestamps();
        });

        Schema::create('mutasi_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_barang_id')->constrained('jenis_barang');
            $table->string('arah', 10);
            $table->string('tipe', 20);
            $table->date('tanggal');
            $table->unsignedInteger('jumlah');
            $table->string('keterangan', 500)->nullable();
            $table->foreignId('teknisi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('ticket')->nullOnDelete();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['jenis_barang_id', 'tanggal']);
        });

        Schema::create('unit_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_barang_id')->constrained('jenis_barang');
            $table->string('kode', 40)->unique();
            $table->foreignId('kondisi_barang_id')->constrained('kondisi_barang');
            $table->foreignId('prefix_registrasi_id')->nullable()->constrained('pengaturan_prefix_registrasi')->nullOnDelete();
            $table->string('status', 20)->default('di_gudang');
            $table->string('serial_number', 100)->nullable();
            $table->timestamps();

            $table->index(['jenis_barang_id', 'status']);
        });

        Schema::create('mutasi_barang_unit', function (Blueprint $table) {
            $table->foreignId('mutasi_barang_id')->constrained('mutasi_barang')->cascadeOnDelete();
            $table->foreignId('unit_barang_id')->constrained('unit_barang')->cascadeOnDelete();
            $table->primary(['mutasi_barang_id', 'unit_barang_id']);
        });

        // Segmen kode bawaan dari contoh bisnis (MDM-NEW-BF-240 / MDM-PGT-240); bisa ditambah di pengaturan.
        DB::table('kategori_barang')->insert(['kode' => 'MDM', 'nama' => 'Modem', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('kondisi_barang')->insert([
            ['kode' => 'NEW', 'nama' => 'Baru', 'created_at' => now(), 'updated_at' => now()],
            ['kode' => 'PGT', 'nama' => 'Pergantian (bekas)', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_barang_unit');
        Schema::dropIfExists('unit_barang');
        Schema::dropIfExists('mutasi_barang');
        Schema::dropIfExists('kode_barang_counter');
        Schema::dropIfExists('jenis_barang');
        Schema::dropIfExists('kondisi_barang');
        Schema::dropIfExists('kategori_barang');
    }
};
