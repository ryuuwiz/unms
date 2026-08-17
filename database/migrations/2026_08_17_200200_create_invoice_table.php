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
        Schema::create('invoice', function (Blueprint $table) {
            $table->id();
            $table->string('no_invoice', 50)->unique();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->foreignId('layanan_pelanggan_id')->constrained('layanan_pelanggan')->cascadeOnDelete();
            $table->decimal('jumlah', 12, 2);
            $table->decimal('jumlah_setelah_promo', 12, 2);
            $table->foreignId('promo_id')->nullable()->constrained('promo')->nullOnDelete();
            $table->string('status', 30)->default('menunggu_pembayaran'); // menunggu_pembayaran, lunas, kadaluarsa, dibatalkan
            $table->date('tanggal_terbit');
            $table->date('tanggal_jatuh_tempo');
            $table->date('tanggal_lunas')->nullable();
            $table->string('metode_pembayaran', 50)->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dihapus_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('keterangan_hapus')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'tanggal_jatuh_tempo']);
        });

        // Add foreign key constraint to promo_penggunaan
        Schema::table('promo_penggunaan', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoice')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promo_penggunaan', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });

        Schema::dropIfExists('invoice');
    }
};
