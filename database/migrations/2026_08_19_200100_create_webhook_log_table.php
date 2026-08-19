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
        Schema::create('webhook_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_payment_gateway_id')->nullable()->constrained('transaksi_payment_gateway')->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('xendit_event_id', 150)->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('status_proses', 30)->default('diterima'); // diterima, diproses, gagal, diabaikan
            $table->text('catatan_error')->nullable();
            $table->timestamp('diterima_pada');
            $table->timestamps();

            $table->index(['event_type', 'status_proses']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_log');
    }
};
