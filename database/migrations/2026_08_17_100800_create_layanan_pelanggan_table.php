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
        Schema::create('layanan_pelanggan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->foreignId('paket_layanan_id')->constrained('paket_layanan');
            $table->foreignId('router_id')->constrained('router');
            $table->string('site_id')->unique()->comment('Auto-generated unique site identifier');
            $table->string('ppp_username')->unique();
            $table->text('ppp_password_terenkripsi')->comment('Encrypted via Laravel encrypted cast');
            $table->string('ip_static')->nullable();
            $table->foreignId('odp_port_id')->nullable()->constrained('odp_port')->nullOnDelete();
            $table->string('jenis_koneksi')->default('pppoe');
            $table->string('status')->default('proses')->index();
            $table->date('tanggal_mulai');
            $table->date('tanggal_expired')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            // Index kritis untuk scheduler invoice generation & suspend
            $table->index(['status', 'tanggal_expired']);
        });

        // Tambahkan FK layanan_pelanggan_id ke odp_port setelah tabel dibuat
        Schema::table('odp_port', function (Blueprint $table) {
            $table->foreign('layanan_pelanggan_id')
                ->references('id')
                ->on('layanan_pelanggan')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('odp_port', function (Blueprint $table) {
            $table->dropForeign(['layanan_pelanggan_id']);
        });
        Schema::dropIfExists('layanan_pelanggan');
    }
};
