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
        Schema::create('odp', function (Blueprint $table) {
            $table->id();
            $table->string('nama_odp')->unique();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahan')->nullOnDelete();
            $table->unsignedTinyInteger('kapasitas_port');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('odp_port', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odp_id')->constrained('odp')->cascadeOnDelete();
            $table->unsignedTinyInteger('nomor_port');
            $table->string('status')->default('kosong');
            // layanan_pelanggan_id diisi setelah tabel layanan_pelanggan dibuat
            $table->unsignedBigInteger('layanan_pelanggan_id')->nullable();
            $table->timestamps();

            $table->unique(['odp_id', 'nomor_port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odp_port');
        Schema::dropIfExists('odp');
    }
};
