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
        Schema::create('log_tugas_terjadwal', function (Blueprint $table) {
            $table->id();
            $table->string('perintah')->index();
            $table->string('status', 20)->index();
            $table->integer('exit_code')->nullable();
            $table->decimal('durasi_detik', 10, 2)->nullable();
            $table->text('output')->nullable();
            $table->timestamp('mulai_at')->nullable();
            $table->timestamp('selesai_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_tugas_terjadwal');
    }
};
