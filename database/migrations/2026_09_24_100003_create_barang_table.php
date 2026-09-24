<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang', 40)->unique()
                ->comment('Format: [Jenis]-[Kondisi]-[Cabang]-[Counter], contoh MDM-NEW-BF-240');
            $table->foreignId('jenis_barang_id')->constrained('pengaturan_jenis_barang');
            $table->foreignId('kondisi_barang_id')->constrained('pengaturan_kondisi_barang');
            $table->foreignId('cabang_barang_id')->constrained('pengaturan_cabang_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('unit');
            $table->integer('stok')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
